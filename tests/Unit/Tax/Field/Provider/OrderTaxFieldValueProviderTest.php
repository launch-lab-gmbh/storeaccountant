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

namespace StoreAccountant\Tests\Unit\Tax\Field\Provider;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use StoreAccountant\Tax\Field\Provider\OrderTaxFieldValueProvider;
use WC_Order;
use function array_map;

/**
 * Tests selected tax field value delegation.
 */
final class OrderTaxFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_tax_value_provider(): void {
		$provider = new OrderTaxFieldValueProvider( new OrderTaxFieldProviderRegistry() );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( OrderTaxFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_tax_fields_for_order_exports_only(): void {
		$provider = new OrderTaxFieldValueProvider( new OrderTaxFieldProviderRegistry() );

		self::assertTrue( $provider->supports( new Field( 'tax_items_total', 'tax_items_total' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertTrue( $provider->supports( new Field( 'tax_19_tax_total', 'tax_19_tax_total' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'order_total', 'order_total' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'tax_19_tax_total', 'tax_19_tax_total' ), new ExportContext( 'customers' ) ) );
	}

	public function test_get_values_delegates_to_selected_tax_provider_and_filters_fields(): void {
		$tax_provider = $this->createMock( OrderTaxFieldProviderInterface::class );
		$tax_provider->expects( self::once() )
			->method( 'get_values' )
			->with( self::isInstanceOf( WC_Order::class ), self::isInstanceOf( ExportContext::class ) )
			->willReturn(
				[
					'tax_items_total'    => new FieldValue( 'tax_items_total', '12.00' ),
					'tax_shipping_total' => new FieldValue( 'tax_shipping_total', '3.00' ),
				]
			);

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_order_tax_field_provider', [] )
			->andReturn( [ $tax_provider ] );
		$tax_provider->method( 'get_id' )->willReturn( 'simple' );

		$values = ( new OrderTaxFieldValueProvider( new OrderTaxFieldProviderRegistry() ) )->get_values(
			new WC_Order(),
			new FieldCollection( [ new Field( 'tax_items_total', 'tax_items_total' ) ] ),
			new ExportContext( OrderExportAdapter::ADAPTER_ID, 0, [], [ 'tax_field_provider_id' => 'simple' ] )
		);

		self::assertSame( [ 'tax_items_total' => '12.00' ], array_map( static fn ( $value ): mixed => $value->value, $values ) );
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$provider = new OrderTaxFieldValueProvider( new OrderTaxFieldProviderRegistry() );

		self::assertSame( [], $provider->get_values( new WC_Order(), new FieldCollection(), new ExportContext( 'customers' ) ) );
		self::assertSame( [], $provider->get_values( 'not-an-order', new FieldCollection(), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
	}
}
