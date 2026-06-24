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

namespace StoreAccountant\Export\Contract;

use WP_Error;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds stable item ID snapshots for queued exports.
 */
interface SnapshotExportAdapterInterface extends BatchExportAdapterInterface {
	/**
	 * Gets all matching source item IDs at export start.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, int|string>|WP_Error
	 */
	public function get_item_ids( ExportPayload $payload ): array|WP_Error;

	/**
	 * Gets source items for one snapshot ID slice.
	 *
	 * @param ExportPayload          $payload  Export payload.
	 * @param array<int, int|string> $item_ids Snapshot item IDs.
	 *
	 * @return iterable<mixed>|WP_Error
	 */
	public function get_items_by_ids( ExportPayload $payload, array $item_ids ): iterable|WP_Error;
}
