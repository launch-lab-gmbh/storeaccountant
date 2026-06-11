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

namespace StoreAccountant\Export\Filter\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies a configured filter to an export-type specific query object.
 */
interface ExportFilterInterface extends RegistryItemInterface {
	/**
	 * Checks whether the filter can be used by an export type.
	 *
	 * @param string $export_type Export adapter identifier.
	 */
	public function supports( string $export_type ): bool;

	/**
	 * Applies the configured filter to a query object.
	 *
	 * @param mixed                 $query     Export-type specific query object.
	 * @param ExportFilterSelection $selection Filter selection.
	 * @param ExportPayload         $payload   Export payload.
	 *
	 * @return true|WP_Error
	 */
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error;
}
