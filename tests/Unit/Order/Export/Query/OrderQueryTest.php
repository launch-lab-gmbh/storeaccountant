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

namespace StoreAccountant\Tests\Unit\Order\Export\Query;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Query\OrderQuery;
use WP_Error;

/**
 * Tests WooCommerce order export queries.
 */
final class OrderQueryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		\WC_Order::$orders        = [
			11 => [ 'id' => 11 ],
			12 => [ 'id' => 12 ],
		];
		\WC_Order_Query::$queries = [];
		\WC_Order_Query::$results = [ 11, '12', 'bad', 0 ];

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wc_get_orders' )->alias( static fn (): array => [] );
		Functions\when( 'wc_get_order' )->alias( static fn ( int $order_id ): \WC_Order => new \WC_Order( $order_id ) );
	}

	protected function tearDown(): void {
		\WC_Order::$orders        = [];
		\WC_Order_Query::$queries = [];
		\WC_Order_Query::$results = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_orders_builds_hpos_compatible_query_and_returns_orders(): void {
		$orders = $this->query()->get_orders( new ExportPayload( 55, OrderExportAdapter::ADAPTER_ID ) );

		self::assertIsArray( $orders );
		self::assertSame( [ 11, 12 ], array_map( static fn ( \WC_Order $order ): int => $order->get_id(), $orders ) );
		self::assertSame(
			[
				'limit'    => -1,
				'offset'   => 0,
				'paginate' => false,
				'return'   => 'ids',
				'type'     => 'shop_order',
				'orderby'  => 'ID',
				'order'    => 'ASC',
			],
			\WC_Order_Query::$queries[0]
		);
	}

	public function test_count_orders_uses_paginated_query_total(): void {
		\WC_Order_Query::$results = (object) [ 'total' => '37' ];

		self::assertSame( 37, $this->query()->count_orders( new ExportPayload( 55, OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( 1, \WC_Order_Query::$queries[0]['limit'] );
		self::assertSame( 0, \WC_Order_Query::$queries[0]['offset'] );
		self::assertTrue( \WC_Order_Query::$queries[0]['paginate'] );
	}

	public function test_get_order_batch_uses_limit_and_offset(): void {
		$this->query()->get_order_batch( new ExportPayload( 55, OrderExportAdapter::ADAPTER_ID ), 20, 10 );

		self::assertSame( 10, \WC_Order_Query::$queries[0]['limit'] );
		self::assertSame( 20, \WC_Order_Query::$queries[0]['offset'] );
		self::assertFalse( \WC_Order_Query::$queries[0]['paginate'] );
	}

	public function test_filters_are_applied_to_order_query_args(): void {
		$this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'status';
				}

				public function supports( string $export_type ): bool {
					return OrderExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->set( 'status', [ 'wc-processing' ] );

					return true;
				}
			}
		)->get_orders( new ExportPayload( 55, OrderExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'status' ) ] ) );

		self::assertSame( [ 'wc-processing' ], \WC_Order_Query::$queries[0]['status'] ?? null );
	}

	public function test_filter_errors_are_returned_without_loading_orders(): void {
		$result = $this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'broken';
				}

				public function supports( string $export_type ): bool {
					return true;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): WP_Error {
					return new WP_Error( 'broken_filter' );
				}
			}
		)->get_orders( new ExportPayload( 55, OrderExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'broken' ) ] ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'broken_filter', $result->get_error_code() );
	}

	private function query( ?ExportFilterInterface $filter = null ): OrderQuery {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_export_filter' === $hook && null !== $filter ? [ $filter ] : $value
		);

		return new OrderQuery( new ExportFilterRegistry() );
	}
}
