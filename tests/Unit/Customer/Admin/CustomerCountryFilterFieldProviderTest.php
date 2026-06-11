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

namespace StoreAccountant\Tests\Unit\Customer\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Admin\CustomerCountryFilterFieldProvider;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Filter\CustomerCountryFilter;
use StoreAccountant\Export\Filter\ExportFilterSelection;

/**
 * Tests the customer country filter field provider.
 */
final class CustomerCountryFilterFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_common_wordpress_functions();

		\WC_Customer::$customers = [
			10 => [
				'id'               => 10,
				'order_count'      => 2,
				'billing_country'  => 'DE',
				'shipping_country' => 'AT',
			],
			11 => [
				'id'               => 11,
				'order_count'      => 1,
				'billing_country'  => 'US',
				'shipping_country' => '',
			],
			12 => [
				'id'               => 12,
				'order_count'      => 0,
				'billing_country'  => 'FR',
				'shipping_country' => 'FR',
			],
		];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [ 10, 11, 12 ];
	}

	protected function tearDown(): void {
		\WC_Customer::$customers = [];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_filter_field_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new CustomerCountryFilterFieldProvider() )->register();

		self::assertTrue( true );
	}

	public function test_get_id_and_supports_are_stable(): void {
		$provider = new CustomerCountryFilterFieldProvider();

		self::assertSame( CustomerCountryFilter::FILTER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( CustomerExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports( 'orders' ) );
	}

	public function test_get_selection_from_request_sanitizes_country_tokens_and_ignores_unavailable_values(): void {
		$selection = ( new CustomerCountryFilterFieldProvider() )->get_selection_from_request(
			[
				CustomerCountryFilterFieldProvider::FIELD_COUNTRY_FIELD => CustomerCountryFilter::FIELD_BILLING_COUNTRY,
				CustomerCountryFilterFieldProvider::FIELD_COUNTRIES     => [ 'de', 'fr', CustomerCountryFilter::COUNTRY_UNASSIGNED, [ 'bad' ] ],
			]
		);

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( CustomerCountryFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( CustomerCountryFilter::FIELD_BILLING_COUNTRY, $selection->settings['country_field'] );
		self::assertSame( [ 'DE' ], $selection->settings['countries'] );
		self::assertFalse( $selection->settings['all_countries'] );
		self::assertTrue( $selection->settings['include_unassigned'] );
	}

	public function test_get_default_selection_includes_all_billing_countries(): void {
		$selection = ( new CustomerCountryFilterFieldProvider() )->get_default_selection();

		self::assertSame( CustomerCountryFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( CustomerCountryFilter::FIELD_BILLING_COUNTRY, $selection->settings['country_field'] );
		self::assertSame( [], $selection->settings['countries'] );
		self::assertTrue( $selection->settings['all_countries'] );
		self::assertFalse( $selection->settings['include_unassigned'] );
	}

	public function test_render_outputs_country_fields_and_available_woocommerce_countries(): void {
		$output = $this->render(
			static fn (): null => ( new CustomerCountryFilterFieldProvider() )->render(
				new ExportFilterSelection(
					CustomerCountryFilter::FILTER_ID,
					[
						'country_field'      => CustomerCountryFilter::FIELD_SHIPPING_COUNTRY,
						'countries'          => [ 'AT' ],
						'all_countries'      => false,
						'include_unassigned' => true,
					]
				)
			)
		);

		self::assertStringContainsString( 'name="storeaccountant_customer_country_field"', $output );
		self::assertStringContainsString( 'value="billing_country"', $output );
		self::assertMatchesRegularExpression( '/value="shipping_country"\\s+selected="selected"/', $output );
		self::assertStringContainsString( 'name="storeaccountant_customer_countries[]"', $output );
		self::assertStringContainsString( 'value="AT"', $output );
		self::assertStringContainsString( 'Austria', $output );
		self::assertStringContainsString( 'data-selected-countries="[&quot;AT&quot;,&quot;unassigned&quot;]"', $output );
		self::assertStringNotContainsString( 'France', $output );
	}

	/**
	 * @param callable(): void $callback Render callback.
	 */
	private function render( callable $callback ): string {
		ob_start();
		$callback();

		return (string) ob_get_clean();
	}

	private function mock_common_wordpress_functions(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_attr_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'selected' )->alias(
			static function ( mixed $selected, mixed $current = true ): void {
				if ( $selected === $current ) {
					echo ' selected="selected"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( bool $disabled ): void {
				if ( $disabled ) {
					echo ' disabled="disabled"';
				}
			}
		);
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', $value ) ?? '' )
		);
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wc_get_base_location' )->alias( static fn (): array => [ 'country' => 'DE' ] );
		Functions\when( 'WC' )->alias(
			static fn (): object => (object) [
				'countries' => new class() {
					public function get_countries(): array {
						return [
							'DE' => 'Germany',
							'AT' => 'Austria',
							'US' => 'United States',
							'FR' => 'France',
						];
					}
				},
			]
		);
	}
}
