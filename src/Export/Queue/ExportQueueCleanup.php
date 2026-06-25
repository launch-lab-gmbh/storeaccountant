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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cleans stale technical queue state without deleting export records or files.
 */
final readonly class ExportQueueCleanup implements HookRegistrarInterface {
	public const HOOK = 'storeaccountant_cleanup_export_queue';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ExportRepository $repository,
		private BatchExportStore $batch_store
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'ensure_scheduled' ] );
		add_action( self::HOOK, [ $this, 'cleanup' ] );
	}

	/**
	 * Ensures cleanup runs periodically.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', self::HOOK );
		}
	}

	/**
	 * Marks exports stuck in processing as failed after 24 hours.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function cleanup(): void {
		$exports = get_posts(
			[
				'post_type'      => ExportPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);

		foreach ( $exports as $export ) {
			if ( ExportStatus::PROCESSING !== get_post_meta( $export->ID, ExportPostType::META_STATUS, true ) ) {
				continue;
			}

			$started_at = strtotime( (string) get_post_meta( $export->ID, ExportPostType::META_STARTED_AT, true ) . ' UTC' );

			if ( false !== $started_at && $started_at < time() - DAY_IN_SECONDS ) {
				$this->repository->mark_failed(
					$export->ID,
					__( 'The export queue job timed out.', 'storeaccountant' ),
					null,
					[
						'export_id'   => $export->ID,
						'log_message' => 'The export queue job timed out.',
					]
				);
				$this->batch_store->delete_export( $export->ID );
			}
		}
	}
}
