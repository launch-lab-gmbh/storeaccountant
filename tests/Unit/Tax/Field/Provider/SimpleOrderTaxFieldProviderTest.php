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
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Tax\Field\Provider\SimpleOrderTaxFieldProvider;
use WC_Order;
use WC_Order_Item_Tax;
use function array_map;

/**
 * Tests simple order tax field provider behavior.
 */
final class SimpleOrderTaxFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_selectable_tax_provider_and_field_provider(): void {
		$provider = new SimpleOrderTaxFieldProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_order_tax_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY + 10 );

		$provider->register();

		self::assertSame( SimpleOrderTaxFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_selected_simple_order_tax_context_only(): void {
		$provider = new SimpleOrderTaxFieldProvider();

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID, 0, [], [ 'tax_field_provider_id' => SimpleOrderTaxFieldProvider::PROVIDER_ID ] ) ) );
		self::assertFalse( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers', 0, [], [ 'tax_field_provider_id' => SimpleOrderTaxFieldProvider::PROVIDER_ID ] ) ) );
	}

	public function test_get_fields_returns_aggregate_decimal_tax_fields(): void {
		$fields = ( new SimpleOrderTaxFieldProvider() )->get_fields( new ExportContext( OrderExportAdapter::ADAPTER_ID ) );

		self::assertSame( [ 'tax_items_total', 'tax_shipping_total' ], array_keys( $fields ) );
		self::assertInstanceOf( NumberFieldType::class, $fields['tax_items_total']->type );
		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $fields['tax_items_total']->type->format );
	}

	public function test_get_values_aggregates_item_and_shipping_tax_totals(): void {
		$order = new WC_Order(
			[
				new WC_Order_Item_Tax( 1, 19.0, 19.0, 3.0 ),
				new WC_Order_Item_Tax( 2, 7.0, 7.5, 1.25 ),
				'ignored',
			]
		);

		self::assertSame(
			[
				'tax_items_total'    => '26.50',
				'tax_shipping_total' => '4.25',
			],
			array_map(
				static fn ( $value ): mixed => $value->value,
				( new SimpleOrderTaxFieldProvider() )->get_values( $order, new ExportContext( OrderExportAdapter::ADAPTER_ID ) )
			)
		);
	}
}
