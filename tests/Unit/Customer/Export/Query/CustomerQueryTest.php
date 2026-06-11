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

namespace StoreAccountant\Tests\Unit\Customer\Export\Query;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Query\CustomerQuery;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use WP_Error;

/**
 * Tests WooCommerce customer export queries.
 */
final class CustomerQueryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );

		\WC_Customer::$customers = [
			1 => [
				'id'          => 1,
				'order_count' => 2,
			],
			2 => [
				'id'          => 2,
				'order_count' => 0,
			],
			3 => [
				'id'          => 3,
				'order_count' => 4,
			],
		];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [ 1, 2, (object) [ 'ID' => 3 ], 0, 'bad' ];
	}

	protected function tearDown(): void {
		\WC_Customer::$customers = [];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_customers_builds_query_args_and_excludes_customers_without_orders(): void {
		$customers = $this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'criteria';
				}

				public function supports( string $export_type ): bool {
					return CustomerExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->countries                  = [ 'DE', 'AT' ];
					$query->include_all_countries      = false;
					$query->include_unassigned_country = true;

					return true;
				}
			}
		)->get_customers(
			new ExportPayload(
				55,
				CustomerExportAdapter::ADAPTER_ID,
				[
					new ExportFilterSelection( 'criteria' ),
				]
			)
		);

		self::assertIsArray( $customers );
		self::assertCount( 2, $customers );
		self::assertSame( 1, $customers[0]->get_id() );
		self::assertSame( 3, $customers[1]->get_id() );
		self::assertSame(
			[
				'fields'     => [ 'ID' ],
				'number'     => -1,
				'offset'     => 0,
				'meta_query' => [
					[
						'relation' => 'OR',
						[
							'key'     => CustomerQueryCriteria::COUNTRY_FIELD_BILLING,
							'value'   => [ 'DE', 'AT' ],
							'compare' => 'IN',
						],
						[
							'key'     => CustomerQueryCriteria::COUNTRY_FIELD_BILLING,
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => CustomerQueryCriteria::COUNTRY_FIELD_BILLING,
							'value'   => '',
							'compare' => '=',
						],
					],
				],
			],
			\WP_User_Query::$queries[0]
		);
	}

	public function test_count_customers_counts_all_pages_without_pagination_side_effects(): void {
		\WP_User_Query::$results = [ 1, 2, 3 ];

		self::assertSame( 2, $this->query()->count_customers( new ExportPayload( 55, CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( 100, \WP_User_Query::$queries[0]['number'] );
		self::assertSame( 0, \WP_User_Query::$queries[0]['offset'] );
	}

	public function test_get_customer_batch_returns_deterministic_slice_of_eligible_customers(): void {
		\WC_Customer::$customers[4] = [
			'id'          => 4,
			'order_count' => 5,
		];
		\WP_User_Query::$results    = [ 1, 2, 3, 4 ];

		$customers = $this->query()->get_customer_batch( new ExportPayload( 55, CustomerExportAdapter::ADAPTER_ID ), 1, 2 );

		self::assertIsArray( $customers );
		self::assertSame( [ 3, 4 ], array_map( static fn ( \WC_Customer $customer ): int => $customer->get_id(), $customers ) );
	}

	public function test_period_filter_is_translated_to_customer_registration_query_args(): void {
		$this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'period';
				}

				public function supports( string $export_type ): bool {
					return CustomerExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->period = new ExportPeriod( '2026-05-01 00:00:00', '2026-05-31 23:59:59' );

					return true;
				}
			}
		)->get_customers(
			new ExportPayload( 55, CustomerExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'period' ) ] )
		);

		self::assertSame(
			[
				[
					'column'    => 'user_registered',
					'after'     => '2026-05-01 00:00:00',
					'before'    => '2026-05-31 23:59:59',
					'inclusive' => true,
				],
			],
			\WP_User_Query::$queries[0]['date_query']
		);
	}

	public function test_modified_period_filter_is_translated_to_last_update_meta_query(): void {
		$this->query(
			new class() implements ExportFilterInterface {
				public function get_id(): string {
					return 'modified-period';
				}

				public function supports( string $export_type ): bool {
					return CustomerExportAdapter::ADAPTER_ID === $export_type;
				}

				public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true {
					$query->date_field = CustomerQueryCriteria::DATE_FIELD_MODIFIED;
					$query->period     = new ExportPeriod( '2026-05-01 00:00:00', '2026-05-31 23:59:59' );

					return true;
				}
			}
		)->get_customers(
			new ExportPayload( 55, CustomerExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'modified-period' ) ] )
		);

		self::assertSame( 'last_update', \WP_User_Query::$queries[0]['meta_query'][0]['key'] );
		self::assertSame( 'BETWEEN', \WP_User_Query::$queries[0]['meta_query'][0]['compare'] );
		self::assertSame( 'NUMERIC', \WP_User_Query::$queries[0]['meta_query'][0]['type'] );
	}

	public function test_filter_error_is_returned_without_running_query(): void {
		$error = $this->query(
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
		)->get_customers(
			new ExportPayload( 55, CustomerExportAdapter::ADAPTER_ID, [ new ExportFilterSelection( 'broken' ) ] )
		);

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'broken_filter', $error->get_error_code() );
		self::assertSame( [], \WP_User_Query::$queries );
	}

	private function query( ?ExportFilterInterface $filter = null ): CustomerQuery {
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_export_filter' === $hook && null !== $filter ? [ $filter ] : $value
		);

		return new CustomerQuery( new ExportFilterRegistry() );
	}
}
