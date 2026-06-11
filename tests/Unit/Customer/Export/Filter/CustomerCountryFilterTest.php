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

namespace StoreAccountant\Tests\Unit\Customer\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Filter\CustomerCountryFilter;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use WP_Error;

/**
 * Tests customer country filter behavior.
 */
final class CustomerCountryFilterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_country_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$filter = new CustomerCountryFilter();
		$filter->register();

		self::assertSame( CustomerCountryFilter::FILTER_ID, $filter->get_id() );
	}

	public function test_supports_customer_exports_only(): void {
		$filter = new CustomerCountryFilter();

		self::assertTrue( $filter->supports( CustomerExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $filter->supports( 'orders' ) );
	}

	public function test_apply_sanitizes_country_selection_and_updates_query_criteria(): void {
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', $value ) ?? '' )
		);

		$query  = new CustomerQueryCriteria();
		$result = ( new CustomerCountryFilter() )->apply(
			$query,
			new ExportFilterSelection(
				CustomerCountryFilter::FILTER_ID,
				[
					'countries'          => [ 'de', 'AT', CustomerCountryFilter::COUNTRY_UNASSIGNED, CustomerCountryFilter::COUNTRY_ALL, [ 'bad' ], '' ],
					'country_field'      => CustomerCountryFilter::FIELD_SHIPPING_COUNTRY,
					'all_countries'      => false,
					'include_unassigned' => true,
				]
			),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( [ 'DE', 'AT' ], $query->countries );
		self::assertSame( CustomerCountryFilter::FIELD_SHIPPING_COUNTRY, $query->country_field );
		self::assertFalse( $query->include_all_countries );
		self::assertTrue( $query->include_unassigned_country );
	}

	public function test_apply_returns_wp_error_for_invalid_query(): void {
		$result = ( new CustomerCountryFilter() )->apply(
			'not-a-query',
			new ExportFilterSelection( CustomerCountryFilter::FILTER_ID ),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_invalid_customer_query', $result->get_error_code() );
	}

	public function test_static_sanitizers_fallback_to_supported_values(): void {
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_]/', '', $value ) ?? '' )
		);

		self::assertSame( CustomerCountryFilter::FIELD_BILLING_COUNTRY, CustomerCountryFilter::get_country_field( 'unknown' ) );
		self::assertSame( [ 'DE', CustomerCountryFilter::COUNTRY_UNASSIGNED ], CustomerCountryFilter::sanitize_countries( [ 'de', [ 'bad' ], CustomerCountryFilter::COUNTRY_UNASSIGNED ] ) );
	}
}
