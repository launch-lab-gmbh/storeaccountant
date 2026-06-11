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

namespace StoreAccountant\Export\Admin\Period\Contract;

use WP_Error;
use StoreAccountant\Export\ExportPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides period fields for the export form.
 */
interface ExportPeriodFieldProviderInterface extends ExportPeriodViewProviderInterface {
	/**
	 * Renders period fields.
	 *
	 * @param ExportPeriod|null    $period    Current period.
	 * @param array<string, mixed> $selection Current stored selection.
	 * @param bool                 $read_only Whether fields should be rendered read-only.
	 */
	public function render( ?ExportPeriod $period = null, array $selection = [], bool $read_only = false ): void;

	/**
	 * Gets the selected period from submitted request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	public function get_period_from_request( array $request ): ExportPeriod|WP_Error;

	/**
	 * Gets a storable period selection from submitted request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_period_selection_from_request( array $request ): array;

	/**
	 * Resolves a stored period selection to concrete UTC bounds.
	 *
	 * @param array<string, mixed> $selection Stored period selection.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	public function get_period_from_selection( array $selection ): ExportPeriod|WP_Error;

	/**
	 * Checks whether a stored selection should also persist concrete date bounds.
	 *
	 * @param array<string, mixed> $selection Stored period selection.
	 */
	public function stores_concrete_period( array $selection ): bool;

	/**
	 * Gets the default export title suffix for the current selection.
	 */
	public function get_default_title_suffix(): string;
}
