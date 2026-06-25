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

namespace StoreAccountant\Customer\Admin;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Filter\CustomerCountryFilter;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function class_exists;
use function esc_attr;
use function in_array;
use function is_numeric;
use function is_array;
use function is_scalar;
use function implode;
use function strtoupper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the customer country filter fields.
 */
final readonly class CustomerCountryFilterFieldProvider implements ExportFilterFieldProviderInterface, HookRegistrarInterface {
	public const FIELD_COUNTRY_FIELD = 'storeaccountant_customer_country_field';
	public const FIELD_COUNTRIES     = 'storeaccountant_customer_countries';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter_field_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return CustomerCountryFilter::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( string $export_type ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false ): void {
		$country_field      = CustomerCountryFilter::FIELD_BILLING_COUNTRY;
		$countries          = [];
		$include_all        = true;
		$include_unassigned = false;

		if ( null !== $selection ) {
			$country_field      = CustomerCountryFilter::get_country_field( $selection->settings['country_field'] ?? CustomerCountryFilter::FIELD_BILLING_COUNTRY );
			$countries          = isset( $selection->settings['countries'] ) && is_array( $selection->settings['countries'] )
				? CustomerCountryFilter::sanitize_countries( $selection->settings['countries'] )
				: [];
			$include_all        = isset( $selection->settings['all_countries'] ) ? (bool) $selection->settings['all_countries'] : [] === $countries;
			$include_unassigned = isset( $selection->settings['include_unassigned'] ) ? (bool) $selection->settings['include_unassigned'] : in_array( CustomerCountryFilter::COUNTRY_UNASSIGNED, $countries, true );
			$countries          = array_values(
				array_filter(
					$countries,
					static fn ( string $country ): bool => ! in_array( $country, [ CustomerCountryFilter::COUNTRY_ALL, CustomerCountryFilter::COUNTRY_UNASSIGNED ], true )
				)
			);
		}
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-customer-country-field"><?php esc_html_e( 'Customer Country Field', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select id="storeaccountant-customer-country-field" name="<?php echo esc_attr( self::FIELD_COUNTRY_FIELD ); ?>" <?php disabled( $read_only ); ?>>
					<?php foreach ( CustomerCountryFilter::get_country_fields() as $field_id => $label ) : ?>
						<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( $country_field, $field_id ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="storeaccountant-customer-countries"><?php esc_html_e( 'Customer Countries', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( $read_only ) : ?>
					<?php $country_labels = $this->get_selected_country_labels( $country_field, $include_all, $include_unassigned, $countries ); ?>
					<?php if ( [] === $country_labels ) : ?>
						<?php esc_html_e( 'Not set', 'storeaccountant' ); ?>
					<?php else : ?>
						<ul>
							<?php foreach ( $country_labels as $label ) : ?>
								<li><?php echo esc_html( $label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php else : ?>
				<div
					class="storeaccountant-customer-country-token-field"
					data-field-name="<?php echo esc_attr( self::FIELD_COUNTRIES ); ?>"
					data-label="<?php esc_attr_e( 'Customer Countries', 'storeaccountant' ); ?>"
					data-all-value="<?php echo esc_attr( CustomerCountryFilter::COUNTRY_ALL ); ?>"
					data-unassigned-value="<?php echo esc_attr( CustomerCountryFilter::COUNTRY_UNASSIGNED ); ?>"
					data-countries="<?php echo esc_attr( (string) wp_json_encode( $this->get_country_token_options() ) ); ?>"
					data-selected-countries="<?php echo esc_attr( (string) wp_json_encode( $this->get_selected_country_tokens( $include_all, $include_unassigned, $countries ) ) ); ?>"
				></div>
				<select id="storeaccountant-customer-countries" name="<?php echo esc_attr( self::FIELD_COUNTRIES ); ?>[]" multiple="multiple" size="8">
					<option value="<?php echo esc_attr( CustomerCountryFilter::COUNTRY_ALL ); ?>" <?php selected( $include_all ); ?>>
						<?php esc_html_e( 'All countries', 'storeaccountant' ); ?>
					</option>
					<option value="<?php echo esc_attr( CustomerCountryFilter::COUNTRY_UNASSIGNED ); ?>" <?php selected( $include_unassigned ); ?>>
						<?php esc_html_e( 'Unassigned', 'storeaccountant' ); ?>
					</option>
					<?php foreach ( $this->get_country_options() as $country_code => $country_option ) : ?>
						<option
							value="<?php echo esc_attr( $country_code ); ?>"
							data-storeaccountant-customer-country-fields="<?php echo esc_attr( implode( ' ', $country_option['fields'] ) ); ?>"
							<?php selected( in_array( $country_code, $countries, true ) ); ?>
						>
							<?php echo esc_html( $country_option['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Only countries with customer orders are shown.', 'storeaccountant' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_selection_from_request( array $request ): ExportFilterSelection|WP_Error {
		$countries          = isset( $request[ self::FIELD_COUNTRIES ] ) && is_array( $request[ self::FIELD_COUNTRIES ] )
			? CustomerCountryFilter::sanitize_countries( wp_unslash( $request[ self::FIELD_COUNTRIES ] ) )
			: [];
		$country_field      = isset( $request[ self::FIELD_COUNTRY_FIELD ] ) && is_scalar( $request[ self::FIELD_COUNTRY_FIELD ] )
			? CustomerCountryFilter::get_country_field( wp_unslash( $request[ self::FIELD_COUNTRY_FIELD ] ) )
			: CustomerCountryFilter::FIELD_BILLING_COUNTRY;
		$valid_countries    = array_keys( $this->get_countries( $country_field ) );
		$include_all        = [] === $countries || in_array( CustomerCountryFilter::COUNTRY_ALL, $countries, true );
		$include_unassigned = in_array( CustomerCountryFilter::COUNTRY_UNASSIGNED, $countries, true );
		$countries          = array_values(
			array_filter(
				$countries,
				static fn ( string $country ): bool => ! in_array( $country, [ CustomerCountryFilter::COUNTRY_ALL, CustomerCountryFilter::COUNTRY_UNASSIGNED ], true )
			)
		);

		if ( $include_all ) {
			$countries = [];
		}

		return new ExportFilterSelection(
			$this->get_id(),
			[
				'country_field'      => $country_field,
				'countries'          => array_values( array_intersect( $countries, $valid_countries ) ),
				'all_countries'      => $include_all,
				'include_unassigned' => $include_unassigned,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_default_selection(): ExportFilterSelection {
		return new ExportFilterSelection(
			$this->get_id(),
			[
				'country_field'      => CustomerCountryFilter::FIELD_BILLING_COUNTRY,
				'countries'          => [],
				'all_countries'      => true,
				'include_unassigned' => false,
			]
		);
	}

	/**
	 * Gets WooCommerce country labels.
	 *
	 * @param string|null $country_field Optional country field to restrict availability.
	 *
	 * @return array<string, string>
	 */
	private function get_countries( ?string $country_field = null ): array {
		$countries = [];

		foreach ( $this->get_country_options() as $country_code => $country_option ) {
			if ( null !== $country_field && ! in_array( CustomerCountryFilter::get_country_field( $country_field ), $country_option['fields'], true ) ) {
				continue;
			}

			$countries[ $country_code ] = $country_option['label'];
		}

		return $countries;
	}

	/**
	 * Gets country options for the enhanced token field.
	 *
	 * @return array<int, array{value: string, label: string, fields: array<int, string>}>
	 */
	private function get_country_token_options(): array {
		return [
			[
				'value'  => CustomerCountryFilter::COUNTRY_ALL,
				'label'  => __( 'All countries', 'storeaccountant' ),
				'fields' => [],
			],
			[
				'value'  => CustomerCountryFilter::COUNTRY_UNASSIGNED,
				'label'  => __( 'Unassigned', 'storeaccountant' ),
				'fields' => [],
			],
			...array_values(
				array_map(
					static fn ( string $country_code, array $country_option ): array => [
						'value'  => $country_code,
						'label'  => $country_option['label'],
						'fields' => $country_option['fields'],
					],
					array_keys( $this->get_country_options() ),
					$this->get_country_options()
				)
			),
		];
	}

	/**
	 * Gets selected token values for the enhanced token field.
	 *
	 * @param bool               $include_all        Whether all assigned countries are selected.
	 * @param bool               $include_unassigned Whether unassigned customers are selected.
	 * @param array<int, string> $countries          Selected country codes.
	 *
	 * @return array<int, string>
	 */
	private function get_selected_country_tokens( bool $include_all, bool $include_unassigned, array $countries ): array {
		$tokens = [];

		if ( $include_all ) {
			$tokens[] = CustomerCountryFilter::COUNTRY_ALL;
		} else {
			$tokens = array_values( $countries );
		}

		if ( $include_unassigned ) {
			$tokens[] = CustomerCountryFilter::COUNTRY_UNASSIGNED;
		}

		return [] !== $tokens ? $tokens : [ CustomerCountryFilter::COUNTRY_ALL ];
	}

	/**
	 * Gets labels for selected customer countries.
	 *
	 * @param string             $country_field       Selected customer country field.
	 * @param bool               $include_all         Whether all assigned countries are selected.
	 * @param bool               $include_unassigned  Whether unassigned customers are selected.
	 * @param array<int, string> $countries           Selected country codes.
	 *
	 * @return array<int, string>
	 */
	private function get_selected_country_labels( string $country_field, bool $include_all, bool $include_unassigned, array $countries ): array {
		$labels = [];

		if ( $include_all ) {
			$labels[] = __( 'All countries', 'storeaccountant' );
		} else {
			$available_countries = $this->get_countries( $country_field );

			foreach ( $countries as $country ) {
				if ( isset( $available_countries[ $country ] ) ) {
					$labels[] = $available_countries[ $country ];
				}
			}
		}

		if ( $include_unassigned ) {
			$labels[] = __( 'Unassigned', 'storeaccountant' );
		}

		return array_values( $labels );
	}

	/**
	 * Gets selectable country options ordered for the admin field.
	 *
	 * @return array<string, array{label: string, fields: array<int, string>}>
	 */
	private function get_country_options(): array {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->countries ) ) {
			return [];
		}

		$billing_codes  = $this->get_available_country_codes( CustomerCountryFilter::FIELD_BILLING_COUNTRY );
		$shipping_codes = $this->get_available_country_codes( CustomerCountryFilter::FIELD_SHIPPING_COUNTRY );
		$available      = array_values( array_unique( [ ...$billing_codes, ...$shipping_codes ] ) );
		$country_labels = WC()->countries->get_countries();
		$options        = [];
		$base_country   = $this->get_base_country();

		if ( '' !== $base_country && in_array( $base_country, $available, true ) && isset( $country_labels[ $base_country ] ) ) {
			$options[ $base_country ] = [
				'label'  => $country_labels[ $base_country ],
				'fields' => $this->get_country_option_fields( $base_country, $billing_codes, $shipping_codes ),
			];
		}

		foreach ( $country_labels as $country_code => $country_label ) {
			if ( $country_code === $base_country || ! in_array( $country_code, $available, true ) ) {
				continue;
			}

			$options[ $country_code ] = [
				'label'  => $country_label,
				'fields' => $this->get_country_option_fields( $country_code, $billing_codes, $shipping_codes ),
			];
		}

		return $options;
	}

	/**
	 * Gets the country fields where a country is available.
	 *
	 * @param string             $country_code Country code.
	 * @param array<int, string> $billing_codes Billing country codes.
	 * @param array<int, string> $shipping_codes Shipping country codes.
	 *
	 * @return array<int, string>
	 */
	private function get_country_option_fields( string $country_code, array $billing_codes, array $shipping_codes ): array {
		$fields = [];

		if ( in_array( $country_code, $billing_codes, true ) ) {
			$fields[] = CustomerCountryFilter::FIELD_BILLING_COUNTRY;
		}

		if ( in_array( $country_code, $shipping_codes, true ) ) {
			$fields[] = CustomerCountryFilter::FIELD_SHIPPING_COUNTRY;
		}

		return $fields;
	}

	/**
	 * Gets country codes with at least one WooCommerce customer order.
	 *
	 * @param string|null $country_field Optional country field.
	 *
	 * @return array<int, string>
	 */
	private function get_available_country_codes( ?string $country_field = null ): array {
		if ( ! class_exists( \WC_Customer::class ) || ! class_exists( \WP_User_Query::class ) ) {
			return [];
		}

		$query = new \WP_User_Query(
			[
				'fields' => [ 'ID' ],
				'number' => -1,
			]
		);
		$codes = [];

		foreach ( $query->get_results() as $user ) {
			$customer = new \WC_Customer( is_numeric( $user ) ? (int) $user : (int) $user->ID );

			if ( $customer->get_id() <= 0 || $customer->get_order_count() <= 0 ) {
				continue;
			}

			if ( null === $country_field || CustomerCountryFilter::FIELD_BILLING_COUNTRY === $country_field ) {
				$billing_country = $customer->get_billing_country();

				if ( '' !== $billing_country ) {
					$codes[] = strtoupper( $billing_country );
				}
			}

			if ( null === $country_field || CustomerCountryFilter::FIELD_SHIPPING_COUNTRY === $country_field ) {
				$shipping_country = $customer->get_shipping_country();

				if ( '' !== $shipping_country ) {
					$codes[] = strtoupper( $shipping_country );
				}
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Gets the shop base country code.
	 */
	private function get_base_country(): string {
		if ( function_exists( 'wc_get_base_location' ) ) {
			$location = wc_get_base_location();

			return isset( $location['country'] ) && is_scalar( $location['country'] ) ? strtoupper( (string) $location['country'] ) : '';
		}

		return '';
	}
}
