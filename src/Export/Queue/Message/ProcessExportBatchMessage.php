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

namespace StoreAccountant\Export\Queue\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Message that processes one export source batch.
 */
final readonly class ProcessExportBatchMessage {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		public int $export_id,
		public int $batch_number,
		public int $offset,
		public int $limit
	) {}
}
