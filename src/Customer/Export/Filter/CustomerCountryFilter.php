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

namespace StoreAccountant\Customer\Export\Filter;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_scalar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters WooCommerce customers by billing or shipping country.
 */
final readonly class CustomerCountryFilter implements ExportFilterInterface, HookRegistrarInterface {
	public const FILTER_ID              = 'customer_country';
	public const COUNTRY_ALL            = 'all';
	public const COUNTRY_UNASSIGNED     = 'unassigned';
	public const FIELD_BILLING_COUNTRY  = CustomerQueryCriteria::COUNTRY_FIELD_BILLING;
	public const FIELD_SHIPPING_COUNTRY = CustomerQueryCriteria::COUNTRY_FIELD_SHIPPING;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter',
			function ( array $filters ): array {
				$filters[ self::FILTER_ID ] = $this;

				return $filters;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( string $export_type ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error {
		if ( ! $query instanceof CustomerQueryCriteria ) {
			return new WP_Error( 'storeaccountant_invalid_customer_query', __( 'The customer country filter requires a WooCommerce customer query.', 'storeaccountant' ) );
		}

		$countries          = isset( $selection->settings['countries'] ) && is_array( $selection->settings['countries'] ) ? self::sanitize_countries( $selection->settings['countries'] ) : [];
		$include_all        = isset( $selection->settings['all_countries'] ) ? (bool) $selection->settings['all_countries'] : in_array( self::COUNTRY_ALL, $countries, true );
		$include_unassigned = isset( $selection->settings['include_unassigned'] ) ? (bool) $selection->settings['include_unassigned'] : in_array( self::COUNTRY_UNASSIGNED, $countries, true );
		$selected_countries = array_values(
			array_filter(
				$countries,
				static fn ( string $country ): bool => ! in_array( $country, [ self::COUNTRY_ALL, self::COUNTRY_UNASSIGNED ], true )
			)
		);

		$query->countries                  = $selected_countries;
		$query->country_field              = self::get_country_field( $selection->settings['country_field'] ?? self::FIELD_BILLING_COUNTRY );
		$query->include_all_countries      = $include_all;
		$query->include_unassigned_country = $include_unassigned;

		return true;
	}

	/**
	 * Gets supported country field labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_country_fields(): array {
		return [
			self::FIELD_BILLING_COUNTRY  => __( 'Billing country', 'storeaccountant' ),
			self::FIELD_SHIPPING_COUNTRY => __( 'Shipping country', 'storeaccountant' ),
		];
	}

	/**
	 * Sanitizes a requested country field.
	 *
	 * @param mixed $country_field Requested country field.
	 */
	public static function get_country_field( mixed $country_field ): string {
		$country_field = is_scalar( $country_field ) ? sanitize_key( (string) $country_field ) : '';

		return in_array( $country_field, array_keys( self::get_country_fields() ), true ) ? $country_field : self::FIELD_BILLING_COUNTRY;
	}

	/**
	 * Sanitizes country codes.
	 *
	 * @param array<int, mixed> $countries Raw country codes.
	 *
	 * @return array<int, string>
	 */
	public static function sanitize_countries( array $countries ): array {
		return array_values(
			array_filter(
				array_map(
					static function ( mixed $country ): string {
						if ( ! is_scalar( $country ) ) {
							return '';
						}

						$country = sanitize_key( (string) $country );

						return in_array( $country, [ self::COUNTRY_ALL, self::COUNTRY_UNASSIGNED ], true ) ? $country : strtoupper( $country );
					},
					$countries
				),
				static fn ( string $country ): bool => '' !== $country
			)
		);
	}
}
