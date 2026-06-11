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

namespace StoreAccountant\Export\Queue;

use JsonException;
use WP_Error;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use function array_key_exists;
use function closedir;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Temporary attachment streams require PHP resources.
use function fclose;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Temporary attachment streams require PHP resources.
use function fopen;
use function function_exists;
use function is_array;
use function is_dir;
use function is_file;
use function is_iterable;
use function is_resource;
use function is_string;
use function json_decode;
use function ksort;
use function opendir;
use function preg_match;
use function readdir;
use function sprintf;
use function stream_copy_to_stream;
use function trailingslashit;
use function wp_json_encode;
use function wp_delete_file;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores normalized export batch fragments until finalization.
 */
final readonly class BatchExportStore {
	private const DIRECTORY = 'storeaccountant/tmp/exports';

	private const INDEX_FILE = 'index.html';

	private const HTACCESS_FILE = '.htaccess';

	private const HTACCESS_CONTENT = 'deny from all';

	/**
	 * Saves one normalized export batch fragment.
	 *
	 * @param int           $export_id    Export post ID.
	 * @param int           $batch_number One-based batch number.
	 * @param ExportDataset $dataset      Normalized batch dataset.
	 *
	 * @return true|WP_Error
	 */
	public function save_batch( int $export_id, int $batch_number, ExportDataset $dataset ): true|WP_Error {
		$directory = $this->get_export_directory( $export_id );

		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$attachments = [];

		foreach ( $dataset->attachments as $index => $attachment ) {
			if ( ! $attachment instanceof ExportAttachment || ! is_resource( $attachment->stream ) ) {
				continue;
			}

			$attachment_path = trailingslashit( $directory ) . sprintf( 'batch-%05d-attachment-%05d.bin', $batch_number, (int) $index + 1 );
			$target_stream   = $this->open_write_stream( $attachment_path );

			if ( false === $target_stream ) {
				return new WP_Error(
					'storeaccountant_export_batch_attachment_write_failed',
					__( 'StoreAccountant could not write a temporary export attachment.', 'storeaccountant' )
				);
			}

			try {
				stream_copy_to_stream( $attachment->stream, $target_stream );
			} finally {
				$this->close_stream( $target_stream );
				$this->close_stream( $attachment->stream );
			}

			$attachments[] = [
				'path'          => $attachment_path,
				'file_name'     => $attachment->file_name,
				'mime_type'     => $attachment->mime_type,
				'internal_path' => $attachment->internal_path,
			];
		}

		$contents = $this->encode_batch( $dataset, $attachments );

		if ( false === $contents ) {
			return new WP_Error(
				'storeaccountant_export_batch_encode_failed',
				__( 'StoreAccountant could not encode the temporary export batch.', 'storeaccountant' )
			);
		}

		$stored = WordPressFilesystem::put_contents( $this->get_batch_path( $export_id, $batch_number ), $contents );

		if ( ! $stored ) {
			return new WP_Error(
				'storeaccountant_export_batch_write_failed',
				__( 'StoreAccountant could not write the temporary export batch.', 'storeaccountant' )
			);
		}

		return true;
	}

	/**
	 * Loads all saved batch fragments as one iterable dataset.
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return ExportDataset|WP_Error
	 */
	public function load_dataset( int $export_id ): ExportDataset|WP_Error {
		$batches = $this->get_batches( $export_id );

		if ( [] === $batches ) {
			return new WP_Error(
				'storeaccountant_export_batches_missing',
				__( 'StoreAccountant could not find temporary export batches.', 'storeaccountant' )
			);
		}

		$first = $this->read_batch( (string) reset( $batches ) );

		if ( is_wp_error( $first ) ) {
			return $first;
		}

		return new ExportDataset(
			$first['fields'],
			$this->record_generator( $batches ),
			$this->attachment_generator( $batches ),
			is_array( $first['options'] ?? null ) ? $first['options'] : []
		);
	}

	/**
	 * Deletes all temporary files for one export.
	 *
	 * @param int $export_id Export post ID.
	 */
	public function delete_export( int $export_id ): void {
		$directory = $this->get_export_directory_path( $export_id );

		if ( is_dir( $directory ) ) {
			$this->delete_directory( $directory );
		}
	}

	/**
	 * Gets a saved batch fragment path.
	 *
	 * @param int $export_id    Export post ID.
	 * @param int $batch_number One-based batch number.
	 */
	private function get_batch_path( int $export_id, int $batch_number ): string {
		return trailingslashit( $this->get_export_directory_path( $export_id ) ) . sprintf( 'batch-%05d.dat', $batch_number );
	}

	/**
	 * Gets and creates the export temp directory.
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return string|WP_Error
	 */
	private function get_export_directory( int $export_id ): string|WP_Error {
		$directory = $this->get_export_directory_path( $export_id );

		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return new WP_Error(
				'storeaccountant_export_batch_directory_failed',
				__( 'StoreAccountant could not create the temporary export directory.', 'storeaccountant' )
			);
		}

		$this->ensure_protection_file( $this->get_root_directory_path(), self::INDEX_FILE, '' );
		$this->ensure_protection_file( $this->get_root_directory_path(), self::HTACCESS_FILE, self::HTACCESS_CONTENT );
		$this->ensure_protection_file( $directory, self::INDEX_FILE, '' );

		return $directory;
	}

	/**
	 * Gets the export temp directory path without creating it.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_export_directory_path( int $export_id ): string {
		return trailingslashit( $this->get_root_directory_path() ) . (string) $export_id;
	}

	/**
	 * Gets the queue temp root path.
	 */
	private function get_root_directory_path(): string {
		$uploads = wp_upload_dir();
		$base    = is_array( $uploads ) && is_string( $uploads['basedir'] ?? null ) ? $uploads['basedir'] : get_temp_dir();

		return trailingslashit( $base ) . self::DIRECTORY;
	}

	/**
	 * Writes a protection file when absent.
	 *
	 * @param string $directory Directory path.
	 * @param string $file      File name.
	 * @param string $contents  File contents.
	 */
	private function ensure_protection_file( string $directory, string $file, string $contents ): void {
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		$path = trailingslashit( $directory ) . $file;

		if ( ! is_file( $path ) ) {
			WordPressFilesystem::put_contents( $path, $contents );
		}
	}

	/**
	 * Encodes a batch fragment as JSON.
	 *
	 * @param ExportDataset        $dataset     Export dataset.
	 * @param array<int, mixed[]>  $attachments Stored attachment metadata.
	 *
	 * @return string|false
	 */
	private function encode_batch( ExportDataset $dataset, array $attachments ): string|false {
		return wp_json_encode(
			[
				'fields'      => $this->normalize_fields( $dataset->fields ),
				'records'     => $this->normalize_records( $dataset->records ),
				'attachments' => $attachments,
				'options'     => $dataset->options,
			]
		);
	}

	/**
	 * Normalizes export fields for temporary JSON storage.
	 *
	 * @param FieldCollection $fields Dataset fields.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_fields( FieldCollection $fields ): array {
		$normalized = [];

		foreach ( $fields as $field ) {
			$normalized[] = [
				'id'      => $field->id,
				'label'   => $field->label,
				'type'    => $field->type instanceof NumberFieldType ? $field->type->format : $field->type->get_id(),
				'options' => $field->options,
			];
		}

		return $normalized;
	}

	/**
	 * Normalizes export records for temporary JSON storage.
	 *
	 * @param iterable<ExportRecord> $records Dataset records.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_records( iterable $records ): array {
		$normalized = [];

		foreach ( $records as $record ) {
			if ( ! $record instanceof ExportRecord ) {
				continue;
			}

			$values = [];

			foreach ( $record->values as $value ) {
				if ( ! $value instanceof FieldValue ) {
					continue;
				}

				$values[] = [
					'field_id' => $value->field_id,
					'value'    => $value->value,
					'options'  => $value->options,
				];
			}

			$normalized[] = [
				'id'      => $record->id,
				'values'  => $values,
				'options' => $record->options,
			];
		}

		return $normalized;
	}

	/**
	 * Lists saved batch fragments keyed by batch number.
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return array<int, string>
	 */
	private function get_batches( int $export_id ): array {
		$directory = $this->get_export_directory_path( $export_id );

		if ( ! is_dir( $directory ) ) {
			return [];
		}

		$handle = opendir( $directory );

		if ( false === $handle ) {
			return [];
		}

		$batches = [];

		try {
			while ( true ) {
				$file = readdir( $handle );

				if ( false === $file ) {
					break;
				}

				if ( 1 !== preg_match( '/^batch-(\d+)\.dat$/', $file, $matches ) ) {
					continue;
				}

				$batches[ (int) $matches[1] ] = trailingslashit( $directory ) . $file;
			}
		} finally {
			closedir( $handle );
		}

		ksort( $batches );

		return $batches;
	}

	/**
	 * Reads one serialized batch fragment.
	 *
	 * @param string $path Batch fragment path.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function read_batch( string $path ): array|WP_Error {
		$contents = is_file( $path ) ? WordPressFilesystem::get_contents( $path ) : false;

		if ( ! is_string( $contents ) ) {
			return new WP_Error(
				'storeaccountant_export_batch_read_failed',
				__( 'StoreAccountant could not read a temporary export batch.', 'storeaccountant' )
			);
		}

		try {
			$batch = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			return new WP_Error(
				'storeaccountant_export_batch_invalid',
				__( 'StoreAccountant found an invalid temporary export batch.', 'storeaccountant' )
			);
		}

		if ( ! is_array( $batch ) || ! array_key_exists( 'fields', $batch ) || ! array_key_exists( 'records', $batch ) ) {
			return new WP_Error(
				'storeaccountant_export_batch_invalid',
				__( 'StoreAccountant found an invalid temporary export batch.', 'storeaccountant' )
			);
		}

		return [
			'fields'      => $this->hydrate_fields( $batch['fields'] ),
			'records'     => $this->hydrate_records( $batch['records'] ),
			'attachments' => is_array( $batch['attachments'] ?? null ) ? $batch['attachments'] : [],
			'options'     => is_array( $batch['options'] ?? null ) ? $batch['options'] : [],
		];
	}

	/**
	 * Rebuilds a field collection from temporary storage.
	 *
	 * @param mixed $fields Stored field data.
	 */
	private function hydrate_fields( mixed $fields ): FieldCollection {
		if ( ! is_iterable( $fields ) ) {
			return new FieldCollection();
		}

		$hydrated = [];

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id = (string) ( $field['id'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$hydrated[] = new Field(
				$id,
				(string) ( $field['label'] ?? $id ),
				(string) ( $field['type'] ?? '' ),
				is_array( $field['options'] ?? null ) ? $field['options'] : []
			);
		}

		return new FieldCollection( $hydrated );
	}

	/**
	 * Rebuilds export records from temporary storage.
	 *
	 * @param mixed $records Stored record data.
	 *
	 * @return array<int, ExportRecord>
	 */
	private function hydrate_records( mixed $records ): array {
		if ( ! is_iterable( $records ) ) {
			return [];
		}

		$hydrated = [];

		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$values = [];

			if ( is_iterable( $record['values'] ?? null ) ) {
				foreach ( $record['values'] as $value ) {
					if ( ! is_array( $value ) ) {
						continue;
					}

					$field_id = (string) ( $value['field_id'] ?? '' );

					if ( '' === $field_id ) {
						continue;
					}

					$values[] = new FieldValue(
						$field_id,
						$value['value'] ?? null,
						is_array( $value['options'] ?? null ) ? $value['options'] : []
					);
				}
			}

			$hydrated[] = new ExportRecord(
				is_string( $record['id'] ?? null ) && '' !== $record['id'] ? $record['id'] : null,
				$values,
				is_array( $record['options'] ?? null ) ? $record['options'] : []
			);
		}

		return $hydrated;
	}

	/**
	 * Streams records from all fragments.
	 *
	 * @param array<int, string> $batches Batch fragment paths.
	 *
	 * @return iterable<mixed>
	 */
	private function record_generator( array $batches ): iterable {
		foreach ( $batches as $path ) {
			$batch = $this->read_batch( $path );

			if ( is_wp_error( $batch ) || ! is_iterable( $batch['records'] ) ) {
				continue;
			}

			foreach ( $batch['records'] as $record ) {
				yield $record;
			}
		}
	}

	/**
	 * Streams attachments from all fragments.
	 *
	 * @param array<int, string> $batches Batch fragment paths.
	 *
	 * @return iterable<ExportAttachment>
	 */
	private function attachment_generator( array $batches ): iterable {
		foreach ( $batches as $path ) {
			$batch = $this->read_batch( $path );

			if ( is_wp_error( $batch ) || ! is_array( $batch['attachments'] ?? null ) ) {
				continue;
			}

			foreach ( $batch['attachments'] as $attachment ) {
				if ( ! is_array( $attachment ) || ! is_file( (string) ( $attachment['path'] ?? '' ) ) ) {
					continue;
				}

				$stream = $this->open_read_stream( (string) $attachment['path'] );

				if ( false === $stream ) {
					continue;
				}

				yield new ExportAttachment(
					$stream,
					(string) ( $attachment['file_name'] ?? '' ),
					(string) ( $attachment['mime_type'] ?? 'application/octet-stream' ),
					(string) ( $attachment['internal_path'] ?? '' )
				);
			}
		}
	}

	/**
	 * Recursively deletes one managed temp directory.
	 *
	 * @param string $directory Directory path.
	 */
	private function delete_directory( string $directory ): void {
		$files = is_dir( $directory ) ? scandir( $directory ) : false;

		if ( false === $files ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$path = trailingslashit( $directory ) . $file;

			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
				continue;
			}

			if ( is_file( $path ) ) {
				$this->delete_file( $path );
			}
		}

		$this->delete_empty_directory( $directory );
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $path File path.
	 */
	private function delete_file( string $path ): void {
		if ( ! function_exists( 'wp_delete_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		wp_delete_file( $path );
	}

	/**
	 * Opens a write stream for temporary attachment storage.
	 *
	 * @param string $path File path.
	 *
	 * @return resource|false
	 */
	private function open_write_stream( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Temporary attachment storage requires PHP streams.
		return fopen( $path, 'wb' );
	}

	/**
	 * Opens a read stream for temporary attachment storage.
	 *
	 * @param string $path File path.
	 *
	 * @return resource|false
	 */
	private function open_read_stream( string $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Temporary attachment storage requires PHP streams.
		return fopen( $path, 'rb' );
	}

	/**
	 * Closes a PHP stream resource.
	 *
	 * @param mixed $stream Potential stream resource.
	 */
	private function close_stream( mixed $stream ): void {
		if ( is_resource( $stream ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Temporary attachment storage requires PHP streams.
			fclose( $stream );
		}
	}

	/**
	 * Deletes an empty local directory through WP_Filesystem.
	 *
	 * @param string $directory Directory path.
	 */
	private function delete_empty_directory( string $directory ): void {
		WordPressFilesystem::rmdir( $directory );
	}
}
