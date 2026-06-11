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

namespace StoreAccountant\Export\Contract;

use WP_Error;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds deterministic batch access to an export adapter.
 */
interface BatchExportAdapterInterface extends ExportAdapterInterface {
	/**
	 * Counts source items for a saved export.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return int|WP_Error
	 */
	public function count_items( ExportPayload $payload ): int|WP_Error;

	/**
	 * Gets one deterministic source item batch.
	 *
	 * @param ExportPayload $payload Export payload.
	 * @param int           $offset  Zero-based item offset.
	 * @param int           $limit   Batch size.
	 *
	 * @return iterable<mixed>|WP_Error
	 */
	public function get_batch_items( ExportPayload $payload, int $offset, int $limit ): iterable|WP_Error;
}
