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
use StoreAccountant\Export\Filter\ExportFilterSelection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes admin fields for an export filter.
 */
interface ExportFilterFieldProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether the field provider applies to an export type.
	 *
	 * @param string $export_type Export adapter identifier.
	 */
	public function supports( string $export_type ): bool;

	/**
	 * Renders the filter fields.
	 *
	 * @param ExportFilterSelection|null $selection Current selection.
	 * @param bool                       $read_only Whether fields should be rendered read-only.
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false ): void;

	/**
	 * Gets the filter selection from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return ExportFilterSelection|WP_Error
	 */
	public function get_selection_from_request( array $request ): ExportFilterSelection|WP_Error;

	/**
	 * Gets the default filter selection.
	 */
	public function get_default_selection(): ExportFilterSelection;
}
