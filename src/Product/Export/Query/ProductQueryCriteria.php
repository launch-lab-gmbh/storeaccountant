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

namespace StoreAccountant\Product\Export\Query;

use StoreAccountant\Export\ExportPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries resolved product export query criteria.
 */
final class ProductQueryCriteria {
	/**
	 * Selected product creation period.
	 *
	 * @var ExportPeriod|null
	 */
	public ?ExportPeriod $period = null;

	/**
	 * Whether product variations should be exported as separate source rows.
	 *
	 * @var bool
	 */
	public bool $export_variations = false;
}
