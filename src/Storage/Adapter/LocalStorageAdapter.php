<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author      thomas.baier@launch-lab.de
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Storage\Adapter;

use RuntimeException;
use Throwable;
use WP_Error;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\ZipArchive\FilesystemZipArchiveProvider;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageFile;
use StoreAccountant\Storage\StorageFileConfiguration;
use function basename;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Stream resources are required by Flysystem.
use function fclose;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream resources are required by Flysystem.
use function fopen;
use function esc_html;
use function function_exists;
use function is_dir;
use function is_file;
use function is_int;
use function is_resource;
use function is_string;
use function strpos;
use function substr;
use function wp_delete_file;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local storage adapter and Flysystem adapter for a protected zip archive.
 */
final readonly class LocalStorageAdapter implements StorageAdapterInterface, FilesystemAdapter, HookRegistrarInterface {
	public const ENGINE_ID = 'local';

	public function __construct(
		private LocalStorageConfiguration $configuration,
		private ProtectedUploadDirectory $directory = new ProtectedUploadDirectory()
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_storage_adapter',
			function ( array $adapters ): array {
				$adapters[ self::ENGINE_ID ] = $this;

				return $adapters;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ENGINE_ID;
	}

	/**
	 * Gets the Flysystem operator used internally for local zip writes.
	 */
	private function get_filesystem(): FilesystemOperator {
		return new Filesystem( $this );
	}

	/**
	 * {@inheritDoc}
	 */
	public function persist( StorageFileConfiguration $configuration ): string|WP_Error {
		$stream = $this->open_read_stream( $configuration->source_path );

		if ( false === $stream ) {
			return new WP_Error(
				'storeaccountant_storage_source_file_not_readable',
				__( 'StoreAccountant could not read the generated export file for storage.', 'storeaccountant' )
			);
		}

		try {
			$target_path = null !== $configuration->internal_path
				? $configuration->storage_path . '#' . $configuration->internal_path
				: $configuration->storage_path;

			$this->get_filesystem()->writeStream( $target_path, $stream );

			foreach ( $configuration->attachments as $attachment ) {
				try {
					$this->get_filesystem()->writeStream(
						$configuration->storage_path . '#' . $attachment->internal_path,
						$attachment->stream
					);
				} finally {
					$this->close_stream( $attachment->stream );
				}
			}
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'storeaccountant_storage_persist_failed',
				__( 'StoreAccountant could not persist the generated export file.', 'storeaccountant' ),
				[
					'exception' => [
						'class'   => $exception::class,
						'message' => $exception->getMessage(),
						'file'    => $exception->getFile(),
						'line'    => $exception->getLine(),
						'trace'   => $exception->getTraceAsString(),
					],
				]
			);
		} finally {
			$this->close_stream( $stream );
		}

		return $configuration->storage_path;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fileExists( string $path ): bool {
		$reference = LocalStorageReference::from_storage_path( $path );

		if ( '' === $reference->path ) {
			$archive_path = $this->configuration->get_archive_path( $reference->archive_file );

			return ! is_wp_error( $archive_path ) && is_file( $archive_path );
		}

		return $this->get_adapter( $reference->archive_file )->fileExists( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function write( string $path, string $contents, Config $config ): void {
		$reference = $this->get_flysystem_reference( $path );

		$this->get_adapter( $reference->archive_file )->write( $reference->path, $contents, $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeStream( string $path, $contents, Config $config ): void {
		$reference = $this->get_flysystem_reference( $path );

		$this->get_adapter( $reference->archive_file )->writeStream( $reference->path, $contents, $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public function read( string $path ): string {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->read( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function readStream( string $path ) {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->readStream( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $path ): void {
		$this->delete_file( $path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_file( string $storage_path ): void {
		if ( ! $this->file_exists( $storage_path ) ) {
			return;
		}

		$reference = LocalStorageReference::from_storage_path( $storage_path );

		$archive_path = $this->configuration->get_archive_path( $reference->archive_file );

		if ( ! is_wp_error( $archive_path ) && is_file( $archive_path ) ) {
			$this->delete_local_file( $archive_path );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function file_exists( string $storage_path ): bool {
		$reference = LocalStorageReference::from_storage_path( $storage_path );

		try {
			$archive_path = $this->configuration->get_archive_path( $reference->archive_file );

			return ! is_wp_error( $archive_path ) && is_file( $archive_path );
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_file( string $storage_path ): StorageFile|WP_Error {
		$reference = LocalStorageReference::from_storage_path( $storage_path );

		$archive_path = $this->configuration->get_archive_path( $reference->archive_file );

		if ( is_wp_error( $archive_path ) || ! is_file( $archive_path ) ) {
			return new WP_Error(
				'storeaccountant_storage_file_not_readable',
				__( 'StoreAccountant could not read the stored export file.', 'storeaccountant' )
			);
		}

		$stream = $this->open_read_stream( $archive_path );

		if ( false === $stream ) {
			return new WP_Error(
				'storeaccountant_storage_file_not_readable',
				__( 'StoreAccountant could not read the stored export file.', 'storeaccountant' )
			);
		}

		return new StorageFile(
			$stream,
			basename( $reference->archive_file ),
			'application/zip'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function deleteDirectory( string $path ): void {
		$this->delete_directory( $path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_directory( string $storage_path ): void {
		$reference = $this->get_flysystem_reference( $storage_path );

		$this->get_adapter( $reference->archive_file )->deleteDirectory( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function createDirectory( string $path, Config $config ): void {
		$reference = $this->get_flysystem_reference( $path );

		$this->get_adapter( $reference->archive_file )->createDirectory( $reference->path, $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_directory( string $storage_path ): void {
		$this->createDirectory( $storage_path, new Config() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function directoryExists( string $path ): bool {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->directoryExists( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function directory_exists( string $storage_path ): bool {
		try {
			return $this->directoryExists( $storage_path );
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function setVisibility( string $path, string $visibility ): void {
		$reference = $this->get_flysystem_reference( $path );

		$this->get_adapter( $reference->archive_file )->setVisibility( $reference->path, $visibility );
	}

	/**
	 * {@inheritDoc}
	 */
	public function set_visibility( string $storage_path, string $visibility ): void {
		$this->setVisibility( $storage_path, $visibility );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_visibility( string $storage_path ): string|WP_Error {
		try {
			$visibility = $this->visibility( $storage_path )->visibility();
		} catch ( Throwable ) {
			return new WP_Error(
				'storeaccountant_storage_visibility_unavailable',
				__( 'StoreAccountant could not read the stored file visibility.', 'storeaccountant' )
			);
		}

		return is_string( $visibility ) ? $visibility : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function visibility( string $path ): FileAttributes {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->visibility( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function mimeType( string $path ): FileAttributes {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->mimeType( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_mime_type( string $storage_path ): string|WP_Error {
		try {
			$mime_type = $this->mimeType( $storage_path )->mimeType();
		} catch ( Throwable ) {
			return new WP_Error(
				'storeaccountant_storage_mime_type_unavailable',
				__( 'StoreAccountant could not read the stored file MIME type.', 'storeaccountant' )
			);
		}

		return is_string( $mime_type ) && '' !== $mime_type ? $mime_type : 'application/octet-stream';
	}

	/**
	 * {@inheritDoc}
	 */
	public function lastModified( string $path ): FileAttributes {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->lastModified( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_last_modified( string $storage_path ): int|WP_Error {
		try {
			$last_modified = $this->lastModified( $storage_path )->lastModified();
		} catch ( Throwable ) {
			return new WP_Error(
				'storeaccountant_storage_last_modified_unavailable',
				__( 'StoreAccountant could not read when the stored file was last modified.', 'storeaccountant' )
			);
		}

		return is_int( $last_modified ) ? $last_modified : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fileSize( string $path ): FileAttributes {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->fileSize( $reference->path );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_file_size( string $storage_path ): int|WP_Error {
		try {
			$file_size = $this->fileSize( $storage_path )->fileSize();
		} catch ( Throwable ) {
			return new WP_Error(
				'storeaccountant_storage_file_size_unavailable',
				__( 'StoreAccountant could not read the stored file size.', 'storeaccountant' )
			);
		}

		return is_int( $file_size ) ? $file_size : 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function listContents( string $path, bool $deep ): iterable {
		$reference = $this->get_flysystem_reference( $path );

		return $this->get_adapter( $reference->archive_file )->listContents( $reference->path, $deep );
	}

	/**
	 * {@inheritDoc}
	 */
	public function move( string $source, string $destination, Config $config ): void {
		$source_reference      = $this->get_flysystem_reference( $source );
		$destination_reference = $this->get_flysystem_reference( $destination );

		if ( $source_reference->archive_file !== $destination_reference->archive_file ) {
			$stream = $this->get_adapter( $source_reference->archive_file )->readStream( $source_reference->path );

			try {
				$this->get_adapter( $destination_reference->archive_file )->writeStream( $destination_reference->path, $stream, $config );
				$this->get_adapter( $source_reference->archive_file )->delete( $source_reference->path );
			} finally {
				$this->close_stream( $stream );
			}

			return;
		}

		$this->get_adapter( $source_reference->archive_file )->move( $source_reference->path, $destination_reference->path, $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public function copy( string $source, string $destination, Config $config ): void {
		$source_reference      = $this->get_flysystem_reference( $source );
		$destination_reference = $this->get_flysystem_reference( $destination );

		if ( $source_reference->archive_file !== $destination_reference->archive_file ) {
			$stream = $this->get_adapter( $source_reference->archive_file )->readStream( $source_reference->path );

			try {
				$this->get_adapter( $destination_reference->archive_file )->writeStream( $destination_reference->path, $stream, $config );
			} finally {
				$this->close_stream( $stream );
			}

			return;
		}

		$this->get_adapter( $source_reference->archive_file )->copy( $source_reference->path, $destination_reference->path, $config );
	}

	/**
	 * {@inheritDoc}
	 */
	public function ensure(): true|WP_Error {
		$path = $this->configuration->get_root_path();

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		return $this->directory->ensure( $path, $this->configuration->display_root_path );
	}

	/**
	 * Deletes the local archive directory when it contains only managed files.
	 */
	public function delete_if_empty(): void {
		$path = $this->configuration->get_root_path();

		if ( is_wp_error( $path ) || ! is_dir( $path ) ) {
			return;
		}

		$this->directory->delete_if_empty( $path );
	}

	/**
	 * Builds the decorated League zip adapter.
	 *
	 * @throws RuntimeException When the local storage adapter is unavailable.
	 */
	private function get_adapter( string $archive_file ): ZipArchiveAdapter {
		$result = $this->ensure();

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( esc_html( $result->get_error_message() ) );
		}

		$archive_path = $this->configuration->get_archive_path( $archive_file );

		if ( is_wp_error( $archive_path ) ) {
			throw new RuntimeException( esc_html( $archive_path->get_error_message() ) );
		}

		return new ZipArchiveAdapter(
			new FilesystemZipArchiveProvider( $archive_path )
		);
	}

	/**
	 * Gets a reference for Flysystem operations inside a zip archive.
	 *
	 * @param string $path Flysystem path.
	 */
	private function get_flysystem_reference( string $path ): LocalStorageReference {
		$separator_position = strpos( $path, '#' );

		if ( false === $separator_position ) {
			return LocalStorageReference::from_storage_path( $path );
		}

		return LocalStorageReference::for_archive_file(
			substr( $path, 0, $separator_position ),
			substr( $path, $separator_position + 1 )
		);
	}

	/**
	 * Opens a read stream for APIs that require PHP resources.
	 *
	 * @param string $path File path.
	 *
	 * @return resource|false
	 */
	private function open_read_stream( string $path ) {
		if ( ! is_file( $path ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Flysystem requires PHP stream resources.
		return fopen( $path, 'rb' );
	}

	/**
	 * Closes a PHP stream resource.
	 *
	 * @param mixed $stream Potential stream resource.
	 */
	private function close_stream( mixed $stream ): void {
		if ( is_resource( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Flysystem returns PHP stream resources.
			fclose( $stream );
		}
	}

	/**
	 * Deletes a local file through the WordPress helper.
	 *
	 * @param string $path File path.
	 */
	private function delete_local_file( string $path ): void {
		$this->load_file_helpers();
		wp_delete_file( $path );
	}

	/**
	 * Loads WordPress filesystem helper functions.
	 */
	private function load_file_helpers(): void {
		if ( ! function_exists( 'wp_delete_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}
}
