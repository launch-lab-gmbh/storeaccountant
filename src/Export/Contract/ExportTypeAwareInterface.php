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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks a provider as limited to specific export types.
 */
interface ExportTypeAwareInterface {
	/**
	 * Checks whether this provider supports the export type.
	 *
	 * @param string $export_type Export adapter identifier.
	 */
	public function supports_export_type( string $export_type ): bool;
}
