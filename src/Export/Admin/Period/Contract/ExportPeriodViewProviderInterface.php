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

use StoreAccountant\Export\ExportPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats saved export periods for admin display.
 */
interface ExportPeriodViewProviderInterface {
	/**
	 * Formats a saved export period for display.
	 *
	 * @param ExportPeriod $period Export period.
	 */
	public function format_period_label( ExportPeriod $period ): string;
}
