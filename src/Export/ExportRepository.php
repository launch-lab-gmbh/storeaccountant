<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright  LaunchLab GmbH
 * @author     thomas.baier@launch-lab.de
 * @author-uri https://launch-lab.de
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export;

use Throwable;
use WP_Error;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use function array_slice;
use function bin2hex;
use function is_string;
use function random_bytes;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists saved export records.
 */
final readonly class ExportRepository {

	private const DEFAULT_LOG_ENTRY_LIMIT = 250;

	/**
	 * Initializes the repository.
	 *
	 * @param ExportFilterSelectionSerializer $filter_serializer Filter selection serializer.
	 */
	public function __construct(
		private ExportFilterSelectionSerializer $filter_serializer,
		private DownloadPasswordManager $passwords
	) {
	}

	/**
	 * Creates a saved export record.
	 *
	 * @param string                            $title            Export title.
	 * @param array<int, ExportFilterSelection> $filters          Configured export filters.
	 * @param string                            $storage_engine   storage adapter identifier.
	 * @param ExportAdapterInterface            $export_adapter   Export adapter.
	 * @param ExportRendererInterface           $export_writer    Export writer.
	 * @param int                               $triggered_by     User ID that triggered the export.
	 * @param int|null                          $configuration_id Export configuration ID, or null for quick exports.
	 * @param int                               $batch_size       Number of source items to process per batch.
	 * @param array{encrypted:string, hash:string}|null $password_snapshot Password snapshot, or null to resolve from configuration/global settings.
	 *
	 * @return int|WP_Error
	 */
	public function create(
		string $title,
		array $filters,
		string $storage_engine,
		ExportAdapterInterface $export_adapter,
		ExportRendererInterface $export_writer,
		int $triggered_by,
		?int $configuration_id = null,
		int $batch_size = ExportPostType::DEFAULT_BATCH_SIZE,
		?array $password_snapshot = null
	): int|WP_Error {
		if ( $this->exists_with_title( $title ) ) {
			return new WP_Error(
				'storeaccountant_export_title_exists',
				__( 'An accounting export with this title already exists.', 'storeaccountant' )
			);
		}

		$password_snapshot = $password_snapshot ?? $this->passwords->get_effective_snapshot_for_configuration( $configuration_id );

		if ( is_wp_error( $password_snapshot ) ) {
			return $password_snapshot;
		}

			$meta_input = [
				ExportPostType::META_EXPORTED_AT       => current_time( 'mysql', true ),
				ExportPostType::META_STATUS            => ExportStatus::SCHEDULED,
				ExportPostType::META_FILTERS           => $this->filter_serializer->encode( $filters ),
				ExportPostType::META_STORAGE_ENGINE    => $storage_engine,
				ExportPostType::META_EXPORT_ADAPTER    => $export_adapter->get_id(),
				ExportPostType::META_EXPORT_WRITER     => $export_writer->get_id(),
				ExportPostType::META_BATCH_SIZE        => (string) max( ExportPostType::MIN_BATCH_SIZE, $batch_size ),
				ExportPostType::META_PATH              => '',
				ExportPostType::META_TRIGGERED_BY      => (string) $triggered_by,
				ExportPostType::META_TOTAL_ITEMS       => '0',
				ExportPostType::META_PROCESSED_ITEMS   => '0',
				ExportPostType::META_TOTAL_BATCHES     => '1',
				ExportPostType::META_PROCESSED_BATCHES => '0',
				ExportPostType::META_FAILED_BATCHES    => '0',
				ExportPostType::META_CURRENT_STEP      => __( 'Waiting for queue worker.', 'storeaccountant' ),
				ExportPostType::META_ERROR_MESSAGE     => '',
				ExportPostType::META_LOG_ENTRIES       => [],
				ExportPostType::META_STARTED_AT        => '',
				ExportPostType::META_FINISHED_AT       => '',
				ExportPostType::META_DOWNLOAD_TOKEN    => $this->generate_unique_download_token(),
				ExportPostType::META_DOWNLOAD_PASSWORD => $password_snapshot['encrypted'],
				ExportPostType::META_DOWNLOAD_PASSWORD_HASH => $password_snapshot['hash'],
			];

			if ( null !== $configuration_id ) {
				$meta_input[ ExportPostType::META_CONFIGURATION_ID ] = (string) $configuration_id;
			}

			$post_id = wp_insert_post(
				[
					'post_type'   => ExportPostType::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => $triggered_by,
					'meta_input'  => $meta_input,
				],
				true
			);

		return $post_id;
	}

	/**
	 * Generates a unique public download token.
	 */
	private function generate_unique_download_token(): string {
		do {
			$token = bin2hex( random_bytes( 16 ) );
		} while ( $this->exists_with_download_token( $token ) );

		return $token;
	}

	/**
	 * Checks whether a saved export already uses the given download token.
	 */
	private function exists_with_download_token( string $token ): bool {
		$exports = get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => ExportPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);

		foreach ( $exports as $export_id ) {
			if ( (string) get_post_meta( (int) $export_id, ExportPostType::META_DOWNLOAD_TOKEN, true ) === $token ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a saved export already uses the given title.
	 *
	 * @param string $title Export title.
	 */
	public function exists_with_title( string $title ): bool {
		$title = trim( $title );

		if ( '' === $title ) {
			return false;
		}

		$existing = get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => ExportPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'title'          => $title,
			]
		);

		return [] !== $existing;
	}

	/**
	 * Updates the generated export path.
	 *
	 * @param int    $post_id Export post ID.
	 * @param string $path    Generated export path.
	 */
	public function update_path( int $post_id, string $path ): void {
		update_post_meta( $post_id, ExportPostType::META_PATH, $path );
	}

	/**
	 * Gets the lifecycle status for an export.
	 *
	 * @param int $post_id Export post ID.
	 */
	public function get_status( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, ExportPostType::META_STATUS, true );

		if ( ExportStatus::is_valid( $status ) ) {
			return $status;
		}

		$path = (string) get_post_meta( $post_id, ExportPostType::META_PATH, true );

		return '' !== $path ? ExportStatus::COMPLETED : ExportStatus::SCHEDULED;
	}

	/**
	 * Marks an export as queued for background processing.
	 *
	 * @param int $post_id Export post ID.
	 */
	public function mark_queued( int $post_id ): void {
		update_post_meta( $post_id, ExportPostType::META_STATUS, ExportStatus::QUEUED );
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, __( 'Waiting for queue worker.', 'storeaccountant' ) );
		update_post_meta( $post_id, ExportPostType::META_ERROR_MESSAGE, '' );
	}

	/**
	 * Marks an export as processing.
	 *
	 * @param int    $post_id      Export post ID.
	 * @param string $current_step Current processing step.
	 */
	public function mark_processing( int $post_id, string $current_step = '' ): void {
		update_post_meta( $post_id, ExportPostType::META_STATUS, ExportStatus::PROCESSING );
		if ( '' === (string) get_post_meta( $post_id, ExportPostType::META_STARTED_AT, true ) ) {
			update_post_meta( $post_id, ExportPostType::META_STARTED_AT, current_time( 'mysql', true ) );
		}
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, $current_step );
		update_post_meta( $post_id, ExportPostType::META_ERROR_MESSAGE, '' );
	}

	/**
	 * Initializes export progress counters.
	 *
	 * @param int $post_id       Export post ID.
	 * @param int $total_items   Total item count.
	 * @param int $total_batches Total batch count.
	 */
	public function initialize_progress( int $post_id, int $total_items, int $total_batches ): void {
		update_post_meta( $post_id, ExportPostType::META_TOTAL_ITEMS, (string) max( 0, $total_items ) );
		update_post_meta( $post_id, ExportPostType::META_PROCESSED_ITEMS, '0' );
		update_post_meta( $post_id, ExportPostType::META_TOTAL_BATCHES, (string) max( 1, $total_batches ) );
		update_post_meta( $post_id, ExportPostType::META_PROCESSED_BATCHES, '0' );
		update_post_meta( $post_id, ExportPostType::META_FAILED_BATCHES, '0' );
	}

	/**
	 * Resets an export for retry.
	 *
	 * @param int $post_id Export post ID.
	 */
	public function reset_for_retry( int $post_id ): void {
		update_post_meta( $post_id, ExportPostType::META_STATUS, ExportStatus::SCHEDULED );
		update_post_meta( $post_id, ExportPostType::META_PATH, '' );
		update_post_meta( $post_id, ExportPostType::META_TOTAL_ITEMS, '0' );
		update_post_meta( $post_id, ExportPostType::META_PROCESSED_ITEMS, '0' );
		update_post_meta( $post_id, ExportPostType::META_TOTAL_BATCHES, '1' );
		update_post_meta( $post_id, ExportPostType::META_PROCESSED_BATCHES, '0' );
		update_post_meta( $post_id, ExportPostType::META_FAILED_BATCHES, '0' );
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, __( 'Waiting for queue worker.', 'storeaccountant' ) );
		update_post_meta( $post_id, ExportPostType::META_ERROR_MESSAGE, '' );
		update_post_meta( $post_id, ExportPostType::META_STARTED_AT, '' );
		update_post_meta( $post_id, ExportPostType::META_FINISHED_AT, '' );
	}

	/**
	 * Marks one batch as processed.
	 *
	 * @param int $post_id Export post ID.
	 * @param int $items   Processed item count in the batch.
	 */
	public function mark_batch_processed( int $post_id, int $items ): void {
		update_post_meta(
			$post_id,
			ExportPostType::META_PROCESSED_ITEMS,
			(string) ( (int) get_post_meta( $post_id, ExportPostType::META_PROCESSED_ITEMS, true ) + max( 0, $items ) )
		);
		update_post_meta(
			$post_id,
			ExportPostType::META_PROCESSED_BATCHES,
			(string) ( (int) get_post_meta( $post_id, ExportPostType::META_PROCESSED_BATCHES, true ) + 1 )
		);
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, __( 'Processing export batches.', 'storeaccountant' ) );
	}

	/**
	 * Checks whether all batches were processed.
	 *
	 * @param int $post_id Export post ID.
	 */
	public function all_batches_processed( int $post_id ): bool {
		$total_batches     = (int) get_post_meta( $post_id, ExportPostType::META_TOTAL_BATCHES, true );
		$processed_batches = (int) get_post_meta( $post_id, ExportPostType::META_PROCESSED_BATCHES, true );
		$total_items       = (int) get_post_meta( $post_id, ExportPostType::META_TOTAL_ITEMS, true );
		$processed_items   = (int) get_post_meta( $post_id, ExportPostType::META_PROCESSED_ITEMS, true );

		return $total_batches > 0 && $processed_batches >= $total_batches && $processed_items >= $total_items;
	}

	/**
	 * Marks an export as completed.
	 *
	 * @param int $post_id Export post ID.
	 */
	public function mark_completed( int $post_id ): void {
		update_post_meta( $post_id, ExportPostType::META_STATUS, ExportStatus::COMPLETED );
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, __( 'Export file generated.', 'storeaccountant' ) );
		update_post_meta( $post_id, ExportPostType::META_FINISHED_AT, current_time( 'mysql', true ) );
	}

	/**
	 * Marks an export as failed.
	 *
	 * @param int    $post_id       Export post ID.
	 * @param string $error_message Error message.
	 */
	public function mark_failed( int $post_id, string $error_message, ?Throwable $exception = null, array $context = [] ): void {
		$log_message = isset( $context['log_message'] ) && is_string( $context['log_message'] ) ? $context['log_message'] : $error_message;
		unset( $context['log_message'] );

		update_post_meta( $post_id, ExportPostType::META_STATUS, ExportStatus::FAILED );
		update_post_meta( $post_id, ExportPostType::META_FAILED_BATCHES, '1' );
		update_post_meta( $post_id, ExportPostType::META_CURRENT_STEP, __( 'Export generation failed.', 'storeaccountant' ) );
			update_post_meta( $post_id, ExportPostType::META_ERROR_MESSAGE, sanitize_text_field( $error_message ) );
			update_post_meta( $post_id, ExportPostType::META_FINISHED_AT, current_time( 'mysql', true ) );

			ExportEventDispatcher::dispatch( ExportEvents::FAILED, $post_id, $error_message, $log_message, $context, $exception );
	}

	/**
	 * Marks an export as failed from a WordPress error and logs its technical data.
	 *
	 * @param int                  $post_id Export post ID.
	 * @param WP_Error             $error   WordPress error.
	 * @param array<string, mixed> $context Additional context.
	 */
	public function mark_failed_from_error( int $post_id, WP_Error $error, array $context = [] ): void {
		$error_data = $error->get_error_data();

		if ( null !== $error_data ) {
			$context['wp_error_data'] = $error_data;
		}

		$context['wp_error_code'] = $error->get_error_code();

		$this->mark_failed( $post_id, $error->get_error_message(), null, $context );
	}

	/**
	 * Adds a technical export log entry.
	 *
	 * @param int                  $post_id   Export post ID.
	 * @param string               $level     Log level.
	 * @param string               $message   User-facing or technical message.
	 * @param array<string, mixed> $context   Additional context.
	 * @param Throwable|null       $exception Exception details.
	 */
	public function add_log_entry( int $post_id, string $level, string $message, array $context = [], ?Throwable $exception = null ): void {
		$entries = get_post_meta( $post_id, ExportPostType::META_LOG_ENTRIES, true );
		$entries = is_array( $entries ) ? $entries : [];

		$entry = [
			'time'    => current_time( 'mysql', true ),
			'level'   => sanitize_key( $level ),
			'message' => $message,
			'context' => $context,
		];

		if ( null !== $exception ) {
			$entry['exception'] = [
				'class'   => $exception::class,
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
			];
		}

		$entries[] = $entry;
		$limit     = (int) apply_filters( 'storeaccountant_export_log_entry_limit', self::DEFAULT_LOG_ENTRY_LIMIT, $post_id );
		$entries   = array_slice( $entries, -max( 1, $limit ) );

		update_post_meta( $post_id, ExportPostType::META_LOG_ENTRIES, $entries );
	}
}
