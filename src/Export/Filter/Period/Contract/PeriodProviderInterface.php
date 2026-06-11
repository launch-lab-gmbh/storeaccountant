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

namespace StoreAccountant\Export\Filter\Period\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\ExportPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a stored period selection to concrete bounds.
 */
interface PeriodProviderInterface extends RegistryItemInterface {
	/**
	 * Resolves a stored period selection.
	 *
	 * @param array<string, mixed> $selection Period selection.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	public function resolve( array $selection ): ExportPeriod|WP_Error;

	/**
	 * Formats a period for admin display.
	 *
	 * @param ExportPeriod $period Concrete period.
	 */
	public function format_label( ExportPeriod $period ): string;
}
