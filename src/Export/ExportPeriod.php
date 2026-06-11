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

namespace StoreAccountant\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents the report period selected for an export.
 */
final readonly class ExportPeriod {
	/**
	 * Initializes the period.
	 *
	 * @param string $start_at Report start date and time in UTC MySQL format.
	 * @param string $end_at   Report end date and time in UTC MySQL format.
	 */
	public function __construct(
		public string $start_at,
		public string $end_at
	) {}
}
