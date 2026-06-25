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
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes export datasets row-by-row for streaming renderers.
 */
interface StreamingExportTemplateNormalizerInterface {
	/**
	 * Normalizes a dataset as an iterable row stream.
	 *
	 * @param ExportDataset $dataset Export dataset.
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return iterable<array<string, mixed>>|WP_Error
	 */
	public function normalize_iterable( ExportDataset $dataset, ExportPayload $payload ): iterable|WP_Error;
}
