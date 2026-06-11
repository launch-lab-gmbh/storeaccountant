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

namespace StoreAccountant\Tests\Unit\Customer\Export\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Query\CustomerQuery;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use WP_Error;

/**
 * Tests the customer export adapter.
 */
final class CustomerExportAdapterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );

		\WC_Customer::$customers = [
			5 => [
				'id'          => 5,
				'order_count' => 1,
			],
			6 => [
				'id'          => 6,
				'order_count' => 0,
			],
		];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [ 5, 6 ];
	}

	protected function tearDown(): void {
		\WC_Customer::$customers = [];
		\WP_User_Query::$queries = [];
		\WP_User_Query::$results = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_export_adapter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_adapter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->adapter()->register();

		self::assertTrue( true );
	}

	public function test_get_id_returns_customer_adapter_id(): void {
		self::assertSame( CustomerExportAdapter::ADAPTER_ID, $this->adapter()->get_id() );
	}

	public function test_item_methods_delegate_to_customer_query(): void {
		$payload = new ExportPayload( 42, CustomerExportAdapter::ADAPTER_ID );
		$adapter = $this->adapter();

		self::assertSame( [ 5 ], array_map( static fn ( \WC_Customer $customer ): int => $customer->get_id(), $adapter->get_items( $payload ) ) );
		self::assertSame( 1, $adapter->count_items( $payload ) );
		self::assertSame( [ 5 ], array_map( static fn ( \WC_Customer $customer ): int => $customer->get_id(), $adapter->get_batch_items( $payload, 0, 10 ) ) );
	}

	public function test_get_context_uses_payload_configuration_id_and_array_items(): void {
		$items   = [
			new \WC_Customer(
				[
					'id'          => 5,
					'order_count' => 1,
				]
			),
		];
		$context = $this->adapter()->get_context(
			new ExportPayload(
				42,
				CustomerExportAdapter::ADAPTER_ID,
				[],
				[
					'configuration_id' => 99,
				]
			),
			$items
		);

		self::assertInstanceOf( ExportContext::class, $context );
		self::assertSame( CustomerExportAdapter::ADAPTER_ID, $context->export_type );
		self::assertSame( 99, $context->configuration_id );
		self::assertSame( $items, $context->items );
	}

	public function test_additional_fields_and_values_are_empty_for_customer_adapter(): void {
		$payload = new ExportPayload( 42, CustomerExportAdapter::ADAPTER_ID );
		$context = new ExportContext( CustomerExportAdapter::ADAPTER_ID );
		$adapter = $this->adapter();

		self::assertInstanceOf( FieldCollection::class, $adapter->get_additional_fields( $payload, $context ) );
		self::assertSame( [], $adapter->get_additional_fields( $payload, $context )->all() );
		self::assertSame( [], $adapter->get_additional_values( new \WC_Customer( [ 'id' => 5 ] ), $payload, $context ) );
	}

	public function test_get_record_id_extracts_customer_id(): void {
		$adapter = $this->adapter();

		self::assertSame( '123', $adapter->get_record_id( new \WC_Customer( [ 'id' => 123 ] ) ) );
		self::assertSame( '', $adapter->get_record_id( 'not-a-customer' ) );
	}

	private function adapter(): CustomerExportAdapter {
		return new CustomerExportAdapter( new CustomerQuery( new ExportFilterRegistry() ) );
	}
}
