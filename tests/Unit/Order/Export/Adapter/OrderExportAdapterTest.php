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

namespace StoreAccountant\Tests\Unit\Order\Export\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Query\OrderQuery;
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use WP_Error;

/**
 * Tests the order export adapter.
 */
final class OrderExportAdapterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		\WC_Order::$orders        = [ 11 => [ 'id' => 11 ] ];
		\WC_Order_Query::$queries = [];
		\WC_Order_Query::$results = [ 11 ];

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wc_get_orders' )->alias( static fn (): array => [] );
		Functions\when( 'wc_get_order' )->alias( static fn ( int $order_id ): \WC_Order => new \WC_Order( $order_id ) );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'get_post_meta' )->alias(
			static fn ( int $post_id, string $key, bool $single = false ): string => ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER === $key ? 'simple' : ''
		);
	}

	protected function tearDown(): void {
		\WC_Order::$orders        = [];
		\WC_Order_Query::$queries = [];
		\WC_Order_Query::$results = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_export_adapter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_adapter', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->adapter()->register();

		self::assertTrue( true );
	}

	public function test_get_id_returns_order_adapter_id(): void {
		self::assertSame( OrderExportAdapter::ADAPTER_ID, $this->adapter()->get_id() );
	}

	public function test_item_methods_delegate_to_order_query(): void {
		$payload = new ExportPayload( 42, OrderExportAdapter::ADAPTER_ID );
		$adapter = $this->adapter();

		self::assertSame( [ 11 ], array_map( static fn ( \WC_Order $order ): int => $order->get_id(), $adapter->get_items( $payload ) ) );
		self::assertSame( 0, $adapter->count_items( $payload ) );
		self::assertSame( [ 11 ], array_map( static fn ( \WC_Order $order ): int => $order->get_id(), $adapter->get_batch_items( $payload, 0, 10 ) ) );
	}

	public function test_get_context_uses_configuration_id_and_tax_provider_selection(): void {
		$items   = [ new \WC_Order( [ 'id' => 11 ] ) ];
		$context = $this->adapter()->get_context(
			new ExportPayload( 42, OrderExportAdapter::ADAPTER_ID, [], [ 'configuration_id' => 99 ] ),
			$items
		);

		self::assertInstanceOf( ExportContext::class, $context );
		self::assertSame( OrderExportAdapter::ADAPTER_ID, $context->export_type );
		self::assertSame( 99, $context->configuration_id );
		self::assertSame( $items, $context->items );
		self::assertSame( 'simple', $context->values['tax_field_provider_id'] );
		self::assertSame( [], $context->values['tax_rates'] );
	}

	public function test_get_context_uses_extended_tax_provider_without_configuration(): void {
		$context = $this->adapter()->get_context( new ExportPayload( 42, OrderExportAdapter::ADAPTER_ID ), [] );

		self::assertSame( 0, $context->configuration_id );
		self::assertSame( ExtendedOrderTaxFieldProvider::PROVIDER_ID, $context->values['tax_field_provider_id'] );
	}

	public function test_additional_fields_and_values_are_empty_for_order_adapter(): void {
		$payload = new ExportPayload( 42, OrderExportAdapter::ADAPTER_ID );
		$context = new ExportContext( OrderExportAdapter::ADAPTER_ID );
		$adapter = $this->adapter();

		self::assertInstanceOf( FieldCollection::class, $adapter->get_additional_fields( $payload, $context ) );
		self::assertSame( [], $adapter->get_additional_fields( $payload, $context )->all() );
		self::assertSame( [], $adapter->get_additional_values( new \WC_Order( [ 'id' => 11 ] ), $payload, $context ) );
	}

	public function test_get_record_id_extracts_order_id(): void {
		$adapter = $this->adapter();

		self::assertSame( '123', $adapter->get_record_id( new \WC_Order( [ 'id' => 123 ] ) ) );
		self::assertSame( '', $adapter->get_record_id( 'not-an-order' ) );
	}

	private function adapter(): OrderExportAdapter {
		return new OrderExportAdapter(
			new OrderTaxRateResolver(),
			new OrderQuery( new ExportFilterRegistry() )
		);
	}
}
