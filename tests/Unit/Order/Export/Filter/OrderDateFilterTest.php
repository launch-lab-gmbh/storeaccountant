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

namespace StoreAccountant\Tests\Unit\Order\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Filter\OrderDateFilter;
use WC_Order_Query;
use WP_Error;

/**
 * Tests order date filter behavior.
 */
final class OrderDateFilterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_date_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$filter = new OrderDateFilter( new PeriodProviderRegistry() );
		$filter->register();

		self::assertSame( OrderDateFilter::FILTER_ID, $filter->get_id() );
	}

	public function test_supports_order_exports_only_and_sanitizes_date_field(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );
		$filter = new OrderDateFilter( new PeriodProviderRegistry() );

		self::assertTrue( $filter->supports( OrderExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $filter->supports( 'customers' ) );
		self::assertSame( OrderDateFilter::FIELD_DATE_PAID, OrderDateFilter::get_date_field( OrderDateFilter::FIELD_DATE_PAID ) );
		self::assertSame( OrderDateFilter::FIELD_DATE_CREATED, OrderDateFilter::get_date_field( 'unknown' ) );
	}

	public function test_apply_sets_timestamp_range_from_resolved_period_snapshot(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );
		$query  = new WC_Order_Query();
		$result = ( new OrderDateFilter( new PeriodProviderRegistry() ) )->apply(
			$query,
			new ExportFilterSelection(
				OrderDateFilter::FILTER_ID,
				[
					'date_field'      => OrderDateFilter::FIELD_DATE_PAID,
					'resolved_period' => [
						'start_at' => '2026-05-01 00:00:00',
						'end_at'   => '2026-05-31 23:59:59',
					],
				]
			),
			new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( '1777593600...1780271999', $query->get( OrderDateFilter::FIELD_DATE_PAID ) );
	}

	public function test_apply_returns_wp_error_for_invalid_query_missing_provider_or_invalid_period(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );
		$filter = new OrderDateFilter( new PeriodProviderRegistry() );

		self::assertInstanceOf(
			WP_Error::class,
			$filter->apply( 'not-a-query', new ExportFilterSelection( OrderDateFilter::FILTER_ID ), new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID ) )
		);

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', [] )
			->andReturn( [] );

		$missing_provider = $filter->apply(
			new WC_Order_Query(),
			new ExportFilterSelection( OrderDateFilter::FILTER_ID ),
			new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID )
		);

		self::assertInstanceOf( WP_Error::class, $missing_provider );
		self::assertSame( 'storeaccountant_period_provider_unavailable', $missing_provider->get_error_code() );

		$invalid_period = $filter->apply(
			new WC_Order_Query(),
			new ExportFilterSelection(
				OrderDateFilter::FILTER_ID,
				[
					'resolved_period' => [
						'start_at' => 'not-a-date',
						'end_at'   => 'also-not-a-date',
					],
				]
			),
			new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID )
		);

		self::assertInstanceOf( WP_Error::class, $invalid_period );
		self::assertSame( 'storeaccountant_invalid_period', $invalid_period->get_error_code() );
	}
}
