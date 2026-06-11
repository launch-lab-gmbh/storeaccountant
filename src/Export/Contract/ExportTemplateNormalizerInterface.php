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
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes an export dataset into the selected template structure.
 */
interface ExportTemplateNormalizerInterface {
	/**
	 * Normalizes a dataset for serialization.
	 *
	 * @param ExportDataset $dataset Export dataset.
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function normalize( ExportDataset $dataset, ExportPayload $payload ): array|WP_Error;
}
