<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export\Queue;

use StoreAccountant\Event\Contract\EventSubscriberInterface;
use StoreAccountant\Export\Event\ExportEvents;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes temporary export batch files after terminal export outcomes.
 */
final readonly class ExportTemporaryFilesCleanupSubscriber implements EventSubscriberInterface {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private BatchExportStore $batch_store
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public static function get_subscribed_events(): array {
		return [
			ExportEvents::FAILED->value => [
				[ 'cleanup_failed_export', 20, 1 ],
			],
		];
	}

	/**
	 * Removes temporary batch fragments for a failed export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int $export_id Export post ID.
	 */
	public function cleanup_failed_export( int $export_id ): void {
		$this->batch_store->delete_export( $export_id );
	}
}
