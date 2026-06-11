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

namespace StoreAccountant\Export;

use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\StorageFileConfiguration;
use function function_exists;
use function get_post_meta;
use function is_string;
use function ltrim;
use function preg_replace;
use function sprintf;
use function strtolower;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds storage references for generated export files.
 */
final readonly class ExportStoragePathGenerator {
	private const EXPORT_DIRECTORY = 'exports';

	public function __construct(
		private LocalStorageConfiguration $local_storage_configuration
	) {}

	/**
	 * Builds the storage reference for an export.
	 *
	 * @param int            $post_id            Export post ID.
	 * @param string         $storage_adapter_id Storage adapter ID.
	 * @param ExportArtifact $artifact           Rendered export artifact.
	 */
	public function generate( int $post_id, string $storage_adapter_id, ExportArtifact $artifact ): StorageFileConfiguration {
		$base_name = $this->get_export_base_name( $post_id );
		$file_name = sprintf(
			'%1$s.%2$s',
			$base_name,
			$this->sanitize_file_extension( $artifact->file_extension )
		);

		if ( LocalStorageAdapter::ENGINE_ID === $storage_adapter_id ) {
			// The local adapter persists a zip archive; the rendered artifact is stored inside it.
			$storage_path = sprintf(
				'%1$s/%2$s.zip',
				self::EXPORT_DIRECTORY,
				$base_name
			);
		} else {
			// Remote adapters receive a relative storage key, never a local absolute path.
			$storage_path = sprintf(
				'%1$s/%2$s',
				self::EXPORT_DIRECTORY,
				$file_name
			);
		}

		return new StorageFileConfiguration(
			$storage_path,
			$artifact->source_path,
			$file_name,
			LocalStorageAdapter::ENGINE_ID === $storage_adapter_id ? $file_name : null,
			$artifact->attachments,
			$artifact->mime_type
		);
	}

	/**
	 * Gets a user-facing display path for a storage reference.
	 *
	 * @param string $storage_adapter_id Storage adapter ID.
	 * @param string $storage_path       Storage reference.
	 */
	public function get_display_path( string $storage_adapter_id, string $storage_path ): string {
		if ( LocalStorageAdapter::ENGINE_ID !== $storage_adapter_id ) {
			return $storage_path;
		}

		return trailingslashit( $this->local_storage_configuration->display_root_path ) . ltrim( $storage_path, '/' );
	}

	/**
	 * Gets the sanitized export title used for generated file names.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_export_base_name( int $post_id ): string {
		$token = (string) get_post_meta( $post_id, ExportPostType::META_DOWNLOAD_TOKEN, true );

		if ( '' !== $token ) {
			return $this->sanitize_file_extension( $token );
		}

		$title = get_the_title( $post_id );
		$title = is_string( $title ) && '' !== $title ? $title : 'storeaccountant-export-' . $post_id;
		$name  = $this->slugify_export_title( $title );

		if ( '' === $name ) {
			$name = 'storeaccountant-export-' . $post_id;
		}

		return $name;
	}

	/**
	 * Builds a lowercase filename-safe slug from an export title.
	 *
	 * @param string $title Export title.
	 */
	private function slugify_export_title( string $title ): string {
		if ( function_exists( 'remove_accents' ) ) {
			$title = remove_accents( $title );
		}

		$title = strtolower( $title );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );

		if ( ! is_string( $title ) ) {
			return '';
		}

		return trim( $title, '-' );
	}

	/**
	 * Sanitizes an export file extension.
	 *
	 * @param string $extension File extension.
	 */
	private function sanitize_file_extension( string $extension ): string {
		$extension = strtolower( $extension );
		$extension = preg_replace( '/[^a-z0-9]+/', '', $extension );

		if ( ! is_string( $extension ) || '' === $extension ) {
			return 'dat';
		}

		return $extension;
	}
}
