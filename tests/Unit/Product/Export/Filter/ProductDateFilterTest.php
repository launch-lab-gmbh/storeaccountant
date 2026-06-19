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

namespace StoreAccountant\Tests\Unit\Product\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\Contract\PeriodProviderInterface;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Filter\ProductDateFilter;
use StoreAccountant\Product\Export\Query\ProductQueryCriteria;
use WP_Error;

/**
 * Tests the product date filter.
 */
final class ProductDateFilterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_export_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->filter()->register();

		self::assertTrue( true );
	}

	public function test_get_id_and_supports_are_stable(): void {
		$filter = $this->filter();

		self::assertSame( ProductDateFilter::FILTER_ID, $filter->get_id() );
		self::assertTrue( $filter->supports( ProductExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $filter->supports( 'customers' ) );
	}

	public function test_apply_uses_resolved_period_snapshot_when_available(): void {
		$criteria = new ProductQueryCriteria();

		$result = $this->filter()->apply(
			$criteria,
			new ExportFilterSelection(
				ProductDateFilter::FILTER_ID,
				[
					'resolved_period' => [
						'start_at' => '2026-05-01 00:00:00',
						'end_at'   => '2026-05-31 23:59:59',
					],
				]
			),
			new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertInstanceOf( ExportPeriod::class, $criteria->period );
		self::assertSame( '2026-05-01 00:00:00', $criteria->period->start_at );
		self::assertSame( '2026-05-31 23:59:59', $criteria->period->end_at );
	}

	public function test_apply_resolves_period_through_configured_provider(): void {
		$provider = new class() implements PeriodProviderInterface {
			public function get_id(): string {
				return MonthYearPeriodProvider::PROVIDER_ID;
			}

			public function resolve( array $selection ): ExportPeriod|WP_Error {
				return new ExportPeriod( '2026-06-01 00:00:00', '2026-06-30 23:59:59' );
			}

			public function format_label( ExportPeriod $period ): string {
				return 'June 2026';
			}
		};

		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_export_filter_period_provider' === $hook ? [ $provider ] : $value
		);

		$criteria = new ProductQueryCriteria();
		$result   = $this->filter()->apply(
			$criteria,
			new ExportFilterSelection(
				ProductDateFilter::FILTER_ID,
				[
					'period_provider' => MonthYearPeriodProvider::PROVIDER_ID,
					'period'          => [
						'month' => '6',
						'year'  => 2026,
					],
				]
			),
			new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( '2026-06-01 00:00:00', $criteria->period?->start_at );
	}

	public function test_apply_returns_error_for_wrong_query_type_or_missing_provider(): void {
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );

		$wrong_query = $this->filter()->apply( new \stdClass(), new ExportFilterSelection( ProductDateFilter::FILTER_ID ), new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ) );
		$missing    = $this->filter()->apply( new ProductQueryCriteria(), new ExportFilterSelection( ProductDateFilter::FILTER_ID ), new ExportPayload( 1, ProductExportAdapter::ADAPTER_ID ) );

		self::assertInstanceOf( WP_Error::class, $wrong_query );
		self::assertSame( 'storeaccountant_invalid_product_query', $wrong_query->get_error_code() );
		self::assertInstanceOf( WP_Error::class, $missing );
		self::assertSame( 'storeaccountant_period_provider_unavailable', $missing->get_error_code() );
	}

	private function filter(): ProductDateFilter {
		return new ProductDateFilter( new PeriodProviderRegistry() );
	}
}
