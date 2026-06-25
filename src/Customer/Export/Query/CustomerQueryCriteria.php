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

namespace StoreAccountant\Customer\Export\Query;

use StoreAccountant\Export\ExportPeriod;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries mutable customer query criteria while export filters are applied.
 */
final class CustomerQueryCriteria {
	public const DATE_FIELD_CREATED     = 'date_created';
	public const DATE_FIELD_MODIFIED    = 'date_modified';
	public const COUNTRY_FIELD_BILLING  = 'billing_country';
	public const COUNTRY_FIELD_SHIPPING = 'shipping_country';

	/**
	 * Initializes the customer query criteria.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, string> $countries Selected country codes.
	 */
	public function __construct(
		public ?ExportPeriod $period = null,
		public string $date_field = self::DATE_FIELD_CREATED,
		public array $countries = [],
		public string $country_field = self::COUNTRY_FIELD_BILLING,
		public bool $include_all_countries = false,
		public bool $include_unassigned_country = false
	) {}
}
