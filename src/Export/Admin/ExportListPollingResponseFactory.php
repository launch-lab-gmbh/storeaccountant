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

namespace StoreAccountant\Export\Admin;

use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function absint;
use function add_query_arg;
use function admin_url;
use function apply_filters;
use function array_filter;
use function array_map;
use function current_time;
use function explode;
use function esc_url_raw;
use function get_post_meta;
use function implode;
use function is_numeric;
use function is_scalar;
use function ltrim;
use function max;
use function sanitize_file_name;
use function sanitize_key;
use function sprintf;
use function strtotime;
use function strrpos;
use function strpos;
use function str_replace;
use function strlen;
use function substr;
use function time;
use function wp_create_nonce;
use function wp_date;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds safe export list row data for admin polling responses.
 */
final readonly class ExportListPollingResponseFactory {
	private const DEFAULT_SCHEDULED_WINDOW = 5 * MINUTE_IN_SECONDS;

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private ExportDownloadUrlFactory $download_urls,
		private PermissionChecker $permissions
	) {}

	/**
	 * Builds polling response data for one export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 *
	 * @return array<string, bool|int|string|null>
	 */
	public function create( int $export_id ): array {
		$status            = $this->get_effective_status( $export_id );
		$scheduled_for     = $this->get_scheduled_for( $export_id );
		$download_url      = $this->get_download_url_when_available( $export_id, $status );
		$total_items       = (int) get_post_meta( $export_id, ExportPostType::META_TOTAL_ITEMS, true );
		$processed_items   = (int) get_post_meta( $export_id, ExportPostType::META_PROCESSED_ITEMS, true );
		$total_batches     = (int) get_post_meta( $export_id, ExportPostType::META_TOTAL_BATCHES, true );
		$processed_batches = (int) get_post_meta( $export_id, ExportPostType::META_PROCESSED_BATCHES, true );

		return [
			'id'                => $export_id,
			'status'            => $status,
			'status_label'      => ExportStatus::get_label( $status ),
			'current_step'      => (string) get_post_meta( $export_id, ExportPostType::META_CURRENT_STEP, true ),
			'processed_items'   => max( 0, $processed_items ),
			'total_items'       => max( 0, $total_items ),
			'processed_batches' => max( 0, $processed_batches ),
			'total_batches'     => max( 0, $total_batches ),
			'progress_label'    => $this->format_progress( $processed_batches, $total_batches, $processed_items, $total_items ),
			'download_url'      => $download_url,
			'download_label'    => __( 'Download', 'storeaccountant' ),
			'pollable'          => $this->is_pollable( $status, $scheduled_for, $export_id ),
			'scheduled_for'     => $scheduled_for,
		];
	}

	/**
	 * Checks whether an export should be polled by the admin list.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function is_export_pollable( int $export_id ): bool {
		return $this->is_pollable(
			$this->get_effective_status( $export_id ),
			$this->get_scheduled_for( $export_id ),
			$export_id
		);
	}

	/**
	 * Gets the lifecycle status used by the list table and polling API.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_effective_status( int $export_id ): string {
		$status = (string) get_post_meta( $export_id, ExportPostType::META_STATUS, true );

		if ( ExportStatus::is_valid( $status ) ) {
			return $status;
		}

		return '' !== $this->normalize_storage_path( (string) get_post_meta( $export_id, ExportPostType::META_PATH, true ) )
			? ExportStatus::COMPLETED
			: ExportStatus::SCHEDULED;
	}

	/**
	 * Formats export progress for display in the native list table.
	 */
	private function format_progress( int $processed_batches, int $total_batches, int $processed_items, int $total_items ): string {
		if ( $total_batches <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: %1$d: processed batches, %2$d: total batches, %3$d: processed items, %4$d: total items */
			__( '%1$d / %2$d batches, %3$d / %4$d items', 'storeaccountant' ),
			max( 0, $processed_batches ),
			max( 0, $total_batches ),
			max( 0, $processed_items ),
			max( 0, $total_items )
		);
	}

	/**
	 * Gets a download URL only when the current user can download the generated file.
	 *
	 * @param int    $export_id Export post ID.
	 * @param string $status    Export lifecycle status.
	 */
	private function get_download_url_when_available( int $export_id, string $status ): ?string {
		if ( ExportStatus::COMPLETED !== $status || ! $this->permissions->can( PermissionActionIds::EXPORT_DOWNLOAD, $export_id ) ) {
			return null;
		}

		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $export_id, ExportPostType::META_PATH, true ) );

		if ( '' === $storage_path ) {
			return null;
		}

		$storage_adapter = $this->get_storage_adapter( $export_id );

		if ( null === $storage_adapter || ! $storage_adapter->file_exists( $storage_path ) ) {
			return null;
		}

		return esc_url_raw( $this->download_urls->get_url( $export_id ) );
	}

	/**
	 * Determines whether the export should continue polling.
	 *
	 * @param string      $status        Export lifecycle status.
	 * @param string|null $scheduled_for Scheduled start time, when stored on the export run.
	 * @param int         $export_id     Export post ID.
	 */
	private function is_pollable( string $status, ?string $scheduled_for, int $export_id ): bool {
		if ( ExportStatus::QUEUED === $status || ExportStatus::PROCESSING === $status ) {
			return $this->was_triggered_today( $export_id );
		}

		if ( ExportStatus::SCHEDULED !== $status || null === $scheduled_for ) {
			return false;
		}

		$scheduled_timestamp = $this->parse_scheduled_timestamp( $scheduled_for );

		if ( null === $scheduled_timestamp ) {
			return false;
		}

		/**
		 * Filters the scheduled export polling window in seconds.
		 *
		 * @since 1.0.0
		 *
		 * @param int $window    Polling window in seconds.
		 * @param int $export_id Export post ID.
		 */
		$window = absint(
			apply_filters(
				'storeaccountant_export_polling_scheduled_window_seconds',
				self::DEFAULT_SCHEDULED_WINDOW,
				$export_id
			)
		);

		return $scheduled_timestamp <= time() + $window;
	}

	/**
	 * Checks whether the export run was created on the current WordPress day.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function was_triggered_today( int $export_id ): bool {
		$exported_at = (string) get_post_meta( $export_id, ExportPostType::META_EXPORTED_AT, true );

		if ( '' === $exported_at ) {
			return false;
		}

		$timestamp = strtotime( $exported_at . ' UTC' );

		if ( false === $timestamp ) {
			return false;
		}

		return wp_date( 'Y-m-d', $timestamp ) === current_time( 'Y-m-d' );
	}

	/**
	 * Gets the scheduled start time stored on an export run, if present.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_scheduled_for( int $export_id ): ?string {
		$scheduled_for = get_post_meta( $export_id, ExportPostType::META_SCHEDULED_FOR, true );

		if ( ! is_scalar( $scheduled_for ) ) {
			return null;
		}

		$scheduled_for = (string) $scheduled_for;

		return '' !== $scheduled_for ? $scheduled_for : null;
	}

	/**
	 * Parses a stored scheduled timestamp.
	 *
	 * @param string $scheduled_for Stored time.
	 */
	private function parse_scheduled_timestamp( string $scheduled_for ): ?int {
		if ( is_numeric( $scheduled_for ) ) {
			return (int) $scheduled_for;
		}

		$timestamp = strtotime( $scheduled_for . ' UTC' );

		if ( false === $timestamp ) {
			$timestamp = strtotime( $scheduled_for );
		}

		return false !== $timestamp ? $timestamp : null;
	}

	/**
	 * Gets the storage adapter for an export.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_storage_adapter( int $export_id ): ?StorageAdapterInterface {
		$storage_engine = (string) get_post_meta( $export_id, ExportPostType::META_STORAGE_ENGINE, true );

		if ( '' === $storage_engine ) {
			return null;
		}

		return $this->storage_adapters->get( sanitize_key( $storage_engine ) );
	}

	/**
	 * Normalizes stored storage paths.
	 *
	 * @param string $storage_path Stored storage path.
	 */
	private function normalize_storage_path( string $storage_path ): string {
		$separator_position = strrpos( $storage_path, '#' );

		if ( false === $separator_position ) {
			return $storage_path;
		}

		$archive_path = $this->normalize_storage_archive_path( substr( $storage_path, 0, $separator_position ) );

		if ( '' === $archive_path ) {
			return ltrim( substr( $storage_path, $separator_position + 1 ), '/' );
		}

		return $archive_path;
	}

	/**
	 * Normalizes the archive path segment of legacy combined storage references.
	 *
	 * @param string $archive_path Stored archive path segment.
	 */
	private function normalize_storage_archive_path( string $archive_path ): string {
		$archive_path = str_replace( '\\', '/', $archive_path );
		$marker       = '/storeaccountant/';
		$marker_pos   = strpos( $archive_path, $marker );

		if ( false !== $marker_pos ) {
			$archive_path = substr( $archive_path, $marker_pos + strlen( $marker ) );
		}

		$segments = array_filter(
			explode( '/', $archive_path ),
			static fn ( string $segment ): bool => '' !== $segment && '.' !== $segment && '..' !== $segment
		);
		$segments = array_map(
			static fn ( string $segment ): string => sanitize_file_name( $segment ),
			$segments
		);
		$segments = array_filter(
			$segments,
			static fn ( string $segment ): bool => '' !== $segment
		);

		return implode( '/', $segments );
	}
}
