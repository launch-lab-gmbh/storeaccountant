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
use StoreAccountant\Customer\Export\Filter\CustomerDateFilter;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\Contract\PeriodProviderInterface;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use WP_Error;

/**
 * Tests customer date filter behavior.
 */
final class CustomerDateFilterTest extends TestCase {
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

	public function test_register_adds_customer_date_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$filter = new CustomerDateFilter( new PeriodProviderRegistry() );
		$filter->register();

		self::assertSame( CustomerDateFilter::FILTER_ID, $filter->get_id() );
	}

	public function test_apply_uses_resolved_period_snapshot_without_registry_lookup(): void {
		$query  = new CustomerQueryCriteria();
		$result = ( new CustomerDateFilter( new PeriodProviderRegistry() ) )->apply(
			$query,
			new ExportFilterSelection(
				CustomerDateFilter::FILTER_ID,
				[
					'resolved_period' => [
						'start_at' => '2026-05-01 00:00:00',
						'end_at'   => '2026-05-31 23:59:59',
					],
				]
			),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( CustomerDateFilter::FIELD_DATE_CREATED, $query->date_field );
		self::assertSame( '2026-05-01 00:00:00', $query->period?->start_at );
		self::assertSame( '2026-05-31 23:59:59', $query->period?->end_at );
	}

	public function test_apply_leaves_query_unrestricted_for_all_time_period(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );
		$query  = new CustomerQueryCriteria();
		$result = ( new CustomerDateFilter( new PeriodProviderRegistry() ) )->apply(
			$query,
			new ExportFilterSelection(
				CustomerDateFilter::FILTER_ID,
				[
					'period_provider' => MonthYearPeriodProvider::PROVIDER_ID,
					'period'          => [
						'month' => MonthYearPeriodProvider::PERIOD_ALL_TIME,
					],
					'resolved_period' => [
						'start_at' => '2016-01-01 00:00:00',
						'end_at'   => '2026-06-22 08:08:26',
					],
				]
			),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertNull( $query->period );
	}

	public function test_apply_resolves_period_with_selected_provider(): void {
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => $value );
		$period_provider = $this->createMock( PeriodProviderInterface::class );
		$period_provider->method( 'get_id' )->willReturn( 'custom' );
		$period_provider->expects( self::once() )
			->method( 'resolve' )
			->with( [ 'month' => 'previous' ] )
			->willReturn( new ExportPeriod( '2026-04-01 00:00:00', '2026-04-30 23:59:59' ) );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', [] )
			->andReturn( [ $period_provider ] );

		$query  = new CustomerQueryCriteria();
		$result = ( new CustomerDateFilter( new PeriodProviderRegistry() ) )->apply(
			$query,
			new ExportFilterSelection(
				CustomerDateFilter::FILTER_ID,
				[
					'period_provider' => 'custom',
					'period'          => [ 'month' => 'previous' ],
				]
			),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( '2026-04-01 00:00:00', $query->period?->start_at );
	}

	public function test_apply_returns_wp_error_for_invalid_query_or_missing_provider(): void {
		$filter = new CustomerDateFilter( new PeriodProviderRegistry() );

		self::assertInstanceOf(
			WP_Error::class,
			$filter->apply( 'not-a-query', new ExportFilterSelection( CustomerDateFilter::FILTER_ID ), new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID ) )
		);

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', [] )
			->andReturn( [] );

		$result = $filter->apply(
			new CustomerQueryCriteria(),
			new ExportFilterSelection( CustomerDateFilter::FILTER_ID ),
			new ExportPayload( 1, CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_period_provider_unavailable', $result->get_error_code() );
	}
}
