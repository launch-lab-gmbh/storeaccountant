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
use StoreAccountant\Storage\ProtectedUploadDirectory;
use function array_key_exists;
use function array_slice;
use function count;
use function closedir;
use function dirname;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Temporary attachment streams require PHP resources.
use function fclose;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Temporary attachment streams require PHP resources.
use function fopen;
use function is_array;
use function is_dir;
use function is_file;
use function is_iterable;
use function is_resource;
use function is_scalar;
use function is_string;
use function is_wp_error;
use function json_decode;
use function ksort;
use function opendir;
use function preg_match;
use function readdir;
use function sprintf;
use function stream_copy_to_stream;
use function trailingslashit;
use function trim;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores normalized export batch fragments until finalization.
 */
final readonly class BatchExportStore {
	private const DIRECTORY = 'storeaccountant/tmp/exports';

	private const ATTACHMENT_METADATA_FILE_FORMAT = 'batch-%05d-attachments.dat';

	private const ITEM_ID_SNAPSHOT_FILE = 'item-ids.dat';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ProtectedUploadDirectory $directory = new ProtectedUploadDirectory()
	) {}

	/**
	 * Saves one normalized export batch fragment.
	 *
	 * @since 1.0.0
	 * @internal
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

		$this->save_attachment_metadata( $export_id, $batch_number, $attachments );

		return true;
	}

	/**
	 * Saves the stable source item ID snapshot for a queued export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int                    $export_id Export post ID.
	 * @param array<int, int|string> $item_ids  Snapshot source item IDs.
	 *
	 * @return true|WP_Error
	 */
	public function save_item_ids( int $export_id, array $item_ids ): true|WP_Error {
		$directory = $this->get_export_directory( $export_id );

		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		$contents = wp_json_encode( $this->normalize_item_ids( $item_ids ) );

		if ( false === $contents ) {
			return new WP_Error(
				'storeaccountant_export_item_snapshot_encode_failed',
				__( 'StoreAccountant could not encode the export item snapshot.', 'storeaccountant' )
			);
		}

		if ( ! WordPressFilesystem::put_contents( $this->get_item_id_snapshot_path( $export_id ), $contents ) ) {
			return new WP_Error(
				'storeaccountant_export_item_snapshot_write_failed',
				__( 'StoreAccountant could not write the export item snapshot.', 'storeaccountant' )
			);
		}

		return true;
	}

	/**
	 * Checks whether a stable source item ID snapshot exists for an export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function has_item_id_snapshot( int $export_id ): bool {
		return is_file( $this->get_item_id_snapshot_path( $export_id ) );
	}

	/**
	 * Counts saved source item IDs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function count_item_ids( int $export_id ): int {
		$item_ids = $this->read_item_ids( $export_id );

		return is_wp_error( $item_ids ) ? 0 : count( $item_ids );
	}

	/**
	 * Loads one source item ID slice from the export snapshot.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 * @param int $offset    Zero-based item offset.
	 * @param int $limit     Maximum IDs to load.
	 *
	 * @return array<int, string>|WP_Error
	 */
	public function load_item_ids( int $export_id, int $offset, int $limit ): array|WP_Error {
		$item_ids = $this->read_item_ids( $export_id );

		if ( is_wp_error( $item_ids ) ) {
			return $item_ids;
		}

		return array_slice( $item_ids, max( 0, $offset ), max( 0, $limit ) );
	}

	/**
	 * Loads all saved batch fragments as one iterable dataset.
	 *
	 * @since 1.0.0
	 * @internal
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

		$first = $this->read_batch( (string) reset( $batches ), true, false, false );

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
	 * Counts saved attachments without hydrating export records.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function count_attachments( int $export_id ): int {
		$count = 0;

		foreach ( $this->get_batches( $export_id ) as $batch_number => $path ) {
			foreach ( $this->read_attachment_metadata( (int) $batch_number, $path ) as $attachment ) {
				if ( is_array( $attachment ) && is_file( (string) ( $attachment['path'] ?? '' ) ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Streams one attachment slice from saved fragments.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 * @param int $offset    Zero-based attachment offset.
	 * @param int $limit     Maximum attachments to load.
	 *
	 * @return iterable<ExportAttachment>
	 */
	public function load_attachments( int $export_id, int $offset, int $limit ): iterable {
		if ( $limit <= 0 ) {
			return;
		}

		$seen    = 0;
		$yielded = 0;

		foreach ( $this->get_batches( $export_id ) as $batch_number => $path ) {
			foreach ( $this->read_attachment_metadata( (int) $batch_number, $path ) as $attachment ) {
				$export_attachment = $this->hydrate_attachment( $attachment );

				if ( null === $export_attachment ) {
					continue;
				}

				if ( $seen++ < $offset ) {
					$this->close_stream( $export_attachment->stream );
					continue;
				}

				yield $export_attachment;

				if ( ++$yielded >= $limit ) {
					return;
				}
			}
		}
	}

	/**
	 * Deletes all temporary files for one export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function delete_export( int $export_id ): void {
		$directory = $this->get_export_directory_path( $export_id );

		if ( is_dir( $directory ) ) {
			WordPressFilesystem::delete( $directory, true, 'd' );
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
	 * Gets a saved attachment metadata fragment path.
	 *
	 * @param int $export_id    Export post ID.
	 * @param int $batch_number One-based batch number.
	 */
	private function get_attachment_metadata_path( int $export_id, int $batch_number ): string {
		return trailingslashit( $this->get_export_directory_path( $export_id ) )
			. sprintf( self::ATTACHMENT_METADATA_FILE_FORMAT, $batch_number );
	}

	/**
	 * Gets the saved item ID snapshot path.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_item_id_snapshot_path( int $export_id ): string {
		return trailingslashit( $this->get_export_directory_path( $export_id ) ) . self::ITEM_ID_SNAPSHOT_FILE;
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
		$ensured   = $this->directory->ensure( $directory, $this->get_export_directory_display_path( $export_id ) );

		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

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
	 * Gets the export temp directory display path.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_export_directory_display_path( int $export_id ): string {
		return 'wp-content/uploads/' . self::DIRECTORY . '/' . (string) $export_id;
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
	 * Saves attachment metadata separately so finalization can avoid decoding records.
	 *
	 * @param int                 $export_id    Export post ID.
	 * @param int                 $batch_number One-based batch number.
	 * @param array<int, mixed[]> $attachments  Stored attachment metadata.
	 */
	private function save_attachment_metadata( int $export_id, int $batch_number, array $attachments ): void {
		$contents = wp_json_encode( $attachments );

		if ( false === $contents ) {
			return;
		}

		WordPressFilesystem::put_contents( $this->get_attachment_metadata_path( $export_id, $batch_number ), $contents );
	}

	/**
	 * Reads the stable source item ID snapshot.
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return array<int, string>|WP_Error
	 */
	private function read_item_ids( int $export_id ): array|WP_Error {
		$path     = $this->get_item_id_snapshot_path( $export_id );
		$contents = is_file( $path ) ? WordPressFilesystem::get_contents( $path ) : false;

		if ( ! is_string( $contents ) ) {
			return new WP_Error(
				'storeaccountant_export_item_snapshot_read_failed',
				__( 'StoreAccountant could not read the export item snapshot.', 'storeaccountant' )
			);
		}

		try {
			$item_ids = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			return new WP_Error(
				'storeaccountant_export_item_snapshot_invalid',
				__( 'StoreAccountant found an invalid export item snapshot.', 'storeaccountant' )
			);
		}

		return is_array( $item_ids ) ? $this->normalize_item_ids( $item_ids ) : [];
	}

	/**
	 * Normalizes source item IDs for temporary storage.
	 *
	 * @param array<int, mixed> $item_ids Source item IDs.
	 *
	 * @return array<int, string>
	 */
	private function normalize_item_ids( array $item_ids ): array {
		$normalized = [];
		$seen       = [];

		foreach ( $item_ids as $item_id ) {
			if ( ! is_scalar( $item_id ) ) {
				continue;
			}

			$item_id = trim( (string) $item_id );

			if ( '' === $item_id || isset( $seen[ $item_id ] ) ) {
				continue;
			}

			$seen[ $item_id ] = true;
			$normalized[]     = $item_id;
		}

		return $normalized;
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
	private function read_batch(
		string $path,
		bool $hydrate_fields = true,
		bool $hydrate_records = true,
		bool $include_attachments = true
	): array|WP_Error {
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
			'fields'      => $hydrate_fields ? $this->hydrate_fields( $batch['fields'] ) : new FieldCollection(),
			'records'     => $hydrate_records ? $this->hydrate_records( $batch['records'] ) : [],
			'attachments' => $include_attachments && is_array( $batch['attachments'] ?? null ) ? $batch['attachments'] : [],
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
			$batch = $this->read_batch( $path, false, true, false );

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
		foreach ( $batches as $batch_number => $path ) {
			foreach ( $this->read_attachment_metadata( (int) $batch_number, $path ) as $attachment ) {
				$export_attachment = $this->hydrate_attachment( $attachment );

				if ( null === $export_attachment ) {
					continue;
				}

				yield $export_attachment;
			}
		}
	}

	/**
	 * Rebuilds one temporary attachment from stored metadata.
	 *
	 * @param mixed $attachment Stored attachment metadata.
	 */
	private function hydrate_attachment( mixed $attachment ): ?ExportAttachment {
		if ( ! is_array( $attachment ) || ! is_file( (string) ( $attachment['path'] ?? '' ) ) ) {
			return null;
		}

		$stream = $this->open_read_stream( (string) $attachment['path'] );

		if ( false === $stream ) {
			return null;
		}

		return new ExportAttachment(
			$stream,
			(string) ( $attachment['file_name'] ?? '' ),
			(string) ( $attachment['mime_type'] ?? 'application/octet-stream' ),
			(string) ( $attachment['internal_path'] ?? '' )
		);
	}

	/**
	 * Reads one attachment metadata fragment.
	 *
	 * @param int    $batch_number One-based batch number.
	 * @param string $batch_path   Main batch fragment path.
	 *
	 * @return array<int, mixed>
	 */
	private function read_attachment_metadata( int $batch_number, string $batch_path ): array {
		$metadata_path = trailingslashit( dirname( $batch_path ) )
			. sprintf( self::ATTACHMENT_METADATA_FILE_FORMAT, $batch_number );
		$contents      = is_file( $metadata_path ) ? WordPressFilesystem::get_contents( $metadata_path ) : false;

		if ( is_string( $contents ) ) {
			try {
				$attachments = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );

				if ( is_array( $attachments ) ) {
					return $attachments;
				}
			} catch ( JsonException $exception ) {
				unset( $exception );
				// Fall through to the main batch fragment for older or invalid metadata files.
			}
		}

		$batch = $this->read_batch( $batch_path, false, false, true );

		return is_wp_error( $batch ) || ! is_array( $batch['attachments'] ?? null )
			? []
			: $batch['attachments'];
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
}
