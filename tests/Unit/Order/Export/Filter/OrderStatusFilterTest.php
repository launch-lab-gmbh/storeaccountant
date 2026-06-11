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
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Filter\OrderStatusFilter;
use StoreAccountant\Order\Export\OrderStatusProvider;
use WC_Order_Query;
use WP_Error;

/**
 * Tests order status filter behavior.
 */
final class OrderStatusFilterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_status_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$filter = new OrderStatusFilter( new OrderStatusProvider() );
		$filter->register();

		self::assertSame( OrderStatusFilter::FILTER_ID, $filter->get_id() );
	}

	public function test_supports_order_exports_only(): void {
		$filter = new OrderStatusFilter( new OrderStatusProvider() );

		self::assertTrue( $filter->supports( OrderExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $filter->supports( 'customers' ) );
	}

	public function test_apply_sets_sanitized_statuses_on_query(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-processing' => 'Processing',
					'wc-completed'  => 'Completed',
				]
			);
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( $value ) );

		$query  = new WC_Order_Query();
		$result = ( new OrderStatusFilter( new OrderStatusProvider() ) )->apply(
			$query,
			new ExportFilterSelection( OrderStatusFilter::FILTER_ID, [ 'statuses' => [ 'wc-processing', 'wc-completed', 'missing', 'wc-processing' ] ] ),
			new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID )
		);

		self::assertTrue( $result );
		self::assertSame( [ 'wc-processing', 'wc-completed' ], $query->get( 'status' ) );
	}

	public function test_apply_returns_wp_error_for_invalid_query_or_empty_statuses(): void {
		$filter = new OrderStatusFilter( new OrderStatusProvider() );

		self::assertInstanceOf(
			WP_Error::class,
			$filter->apply( 'not-a-query', new ExportFilterSelection( OrderStatusFilter::FILTER_ID ), new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID ) )
		);

		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn( [ 'wc-processing' => 'Processing' ] );

		$result = $filter->apply(
			new WC_Order_Query(),
			new ExportFilterSelection( OrderStatusFilter::FILTER_ID, [ 'statuses' => [] ] ),
			new ExportPayload( 1, OrderExportAdapter::ADAPTER_ID )
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_invalid_order_statuses', $result->get_error_code() );
	}
}
