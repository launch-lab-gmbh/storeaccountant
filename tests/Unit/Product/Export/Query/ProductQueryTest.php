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

namespace StoreAccountant\Tests\Unit\Product\Export\Query;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Query\ProductQuery;
use StoreAccountant\Product\Export\Query\ProductQueryCriteria;
use WC_Product;
use WP_Error;

/**
 * Tests WooCommerce product export queries.
 */
final class ProductQueryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		\WC_Product::$products             = [
			11 => [ 'id' => 11 ],
			12 => [ 'id' => 12 ],
		];
		\WP_Query::$queries               = [];
		\WP_Query::$results               = [ 11, '12', 'bad', 0 ];
		\WP_Query::$found_posts_result    = 0;

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wc_get_product' )->alias( static fn ( int $product_id ): WC_Product => new WC_Product( $product_id ) );
		Functions\when( 'wc_get_product_statuses' )->alias(
			static fn (): array => [
				'publish' => 'Published',
				'private' => 'Private',
			]
		);
	}

	protected function tearDown(): void {
		\WC_Product::$products          = [];
		\WP_Query::$queries            = [];
		\WP_Query::$results            = [];
		\WP_Query::$found_posts_result = 0;

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_products_builds_product_query_and_returns_products(): void {
		$products = $this->query()->get_products( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID ) );

		self::assertIsArray( $products );
		self::assertSame( [ 11, 12 ], array_map( static fn ( WC_Product $product ): int => $product->get_id(), $products ) );
		self::assertSame(
			[
				'fields'         => 'ids',
				'post_type'      => [ 'product' ],
				'post_status'    => [ 'publish', 'private' ],
				'posts_per_page' => -1,
				'offset'         => 0,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			],
			\WP_Query::$queries[0]
		);
	}

	public function test_count_products_uses_found_posts_from_paginated_query(): void {
		\WP_Query::$found_posts_result = 37;

		self::assertSame( 37, $this->query()->count_products( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( 1, \WP_Query::$queries[0]['posts_per_page'] );
		self::assertSame( 0, \WP_Query::$queries[0]['offset'] );
		self::assertFalse( \WP_Query::$queries[0]['no_found_rows'] );
	}

	public function test_get_product_batch_uses_limit_and_offset(): void {
		$this->query()->get_product_batch( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID ), 20, 10 );

		self::assertSame( 10, \WP_Query::$queries[0]['posts_per_page'] );
		self::assertSame( 20, \WP_Query::$queries[0]['offset'] );
		self::assertTrue( \WP_Query::$queries[0]['no_found_rows'] );
	}

	public function test_filters_are_translated_to_product_query_args(): void {
		$this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'criteria';
				}

				public function supports( string $export_type ): bool {
					return ProductExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->export_variations = true;
					$query->period            = new ExportPeriod( '2026-05-01 00:00:00', '2026-05-31 23:59:59' );

					return true;
				}
			}
		)->get_products( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'criteria' ) ] ) );

		self::assertSame( [ 'product', 'product_variation' ], \WP_Query::$queries[0]['post_type'] );
		self::assertSame(
			[
				[
					'column'    => 'post_date_gmt',
					'after'     => '2026-05-01 00:00:00',
					'before'    => '2026-05-31 23:59:59',
					'inclusive' => true,
				],
			],
			\WP_Query::$queries[0]['date_query']
		);
	}

	public function test_filter_errors_are_returned_without_running_query(): void {
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
		)->get_products( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'broken' ) ] ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'broken_filter', $result->get_error_code() );
		self::assertSame( [], \WP_Query::$queries );
	}

	public function test_invalid_date_period_omits_date_query(): void {
		$this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'invalid-period';
				}

				public function supports( string $export_type ): bool {
					return ProductExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->period = new ExportPeriod( 'not-a-date', 'also-not-a-date' );

					return true;
				}
			}
		)->get_products( new ExportPayload( 55, ProductExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'invalid-period' ) ] ) );

		self::assertSame( [ [] ], \WP_Query::$queries[0]['date_query'] );
	}

	private function query( ?ExportFilterInterface $filter = null ): ProductQuery {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_export_filter' === $hook && null !== $filter ? [ $filter ] : $value
		);

		return new ProductQuery( new ExportFilterRegistry() );
	}
}
