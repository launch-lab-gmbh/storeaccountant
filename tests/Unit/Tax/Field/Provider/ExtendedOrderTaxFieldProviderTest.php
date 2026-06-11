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
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use WC_Order;
use WC_Order_Item_Tax;
use function array_map;

/**
 * Tests extended order tax field provider behavior.
 */
final class ExtendedOrderTaxFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_selectable_tax_provider_and_field_provider(): void {
		$provider = new ExtendedOrderTaxFieldProvider( new OrderTaxRateResolver() );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_order_tax_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY + 10 );

		$provider->register();

		self::assertSame( ExtendedOrderTaxFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_extended_order_tax_context_by_default(): void {
		$provider = new ExtendedOrderTaxFieldProvider( new OrderTaxRateResolver() );

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID, 0, [], [ 'tax_field_provider_id' => 'simple' ] ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_fields_creates_three_decimal_fields_per_valid_tax_rate(): void {
		$fields = ( new ExtendedOrderTaxFieldProvider( new OrderTaxRateResolver() ) )->get_fields(
			new ExportContext(
				OrderExportAdapter::ADAPTER_ID,
				0,
				[],
				[
					'tax_rates' => [
						'19_tax'  => 19,
						'invalid' => 'nope',
					],
				]
			)
		);

		self::assertSame( [ 'tax_19_tax_items', 'tax_19_tax_shipping', 'tax_19_tax_total' ], array_keys( $fields ) );
		self::assertInstanceOf( NumberFieldType::class, $fields['tax_19_tax_total']->type );
		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $fields['tax_19_tax_total']->type->format );
	}

	public function test_get_values_groups_totals_by_tax_rate_key(): void {
		$order   = new WC_Order(
			[
				new WC_Order_Item_Tax( 19, 19.0, 10.0, 2.0 ),
				new WC_Order_Item_Tax( 7, 7.0, 3.5, 0.5 ),
				new WC_Order_Item_Tax( 0, 0.0, 99.0, 99.0 ),
			]
		);
		$context = new ExportContext(
			OrderExportAdapter::ADAPTER_ID,
			0,
			[],
			[
				'tax_rates' => [
					'19_tax' => 19,
					'7_tax'  => 7,
				],
			]
		);

		self::assertSame(
			[
				'tax_19_tax_items'    => '10.00',
				'tax_19_tax_shipping' => '2.00',
				'tax_19_tax_total'    => '12.00',
				'tax_7_tax_items'     => '3.50',
				'tax_7_tax_shipping'  => '0.50',
				'tax_7_tax_total'     => '4.00',
			],
			array_map(
				static fn ( $value ): mixed => $value->value,
				( new ExtendedOrderTaxFieldProvider( new OrderTaxRateResolver() ) )->get_values( $order, $context )
			)
		);
	}
}
