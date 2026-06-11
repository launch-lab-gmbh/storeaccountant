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

namespace StoreAccountant\Tests\Unit\Order\Export\Field\Provider;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Field\Provider\OrderFieldValueProvider;
use WC_DateTime;
use WC_Order;
use function array_map;

/**
 * Tests order field value resolution.
 */
final class OrderFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_value_provider(): void {
		$provider = new OrderFieldValueProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( OrderFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_known_order_fields_only_for_order_exports(): void {
		$provider = new OrderFieldValueProvider();

		self::assertTrue( $provider->supports( new Field( 'order_total', 'order_total' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'unknown', 'unknown' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'order_total', 'order_total' ), new ExportContext( 'customers' ) ) );
	}

	public function test_get_values_maps_order_data_amounts_and_split_addresses(): void {
		$order  = new WC_Order(
			[
				'id'                   => 1001,
				'order_number'         => 'A-1001',
				'date_created'         => new WC_DateTime( '2026-05-03 10:20:30' ),
				'status'               => 'processing',
				'currency'             => 'EUR',
				'payment_method'       => 'stripe',
				'payment_method_title' => 'Credit Card',
				'customer_id'          => 42,
				'billing_address_1'    => 'Market Alley 9b',
				'shipping_address_1'   => 'No Number Road',
				'shipping_phone'       => '+49 456',
				'subtotal'             => '100',
				'discount_total'       => '5.5',
				'shipping_total'       => '4',
				'total'                => '117.81',
				'fees'                 => [
					new class() {
						public function get_total(): string {
							return '1.2';
						}
					},
					new class() {
						public function get_total(): string {
							return '2.3';
						}
					},
				],
			]
		);
		$fields = new FieldCollection(
			[
				new Field( 'order_id', 'order_id' ),
				new Field( 'order_number', 'order_number' ),
				new Field( 'order_date', 'order_date' ),
				new Field( 'billing_street', 'billing_street' ),
				new Field( 'billing_house_number', 'billing_house_number' ),
				new Field( 'shipping_street', 'shipping_street' ),
				new Field( 'shipping_house_number', 'shipping_house_number' ),
				new Field( 'shipping_phone', 'shipping_phone' ),
				new Field( 'order_subtotal', 'order_subtotal' ),
				new Field( 'discount_total', 'discount_total' ),
				new Field( 'shipping_total', 'shipping_total' ),
				new Field( 'fee_total', 'fee_total' ),
				new Field( 'order_total', 'order_total' ),
			]
		);

		self::assertSame(
			[
				'order_id'              => 1001,
				'order_number'          => 'A-1001',
				'order_date'            => '2026-05-03 10:20:30',
				'billing_street'        => 'Market Alley',
				'billing_house_number'  => '9b',
				'shipping_street'       => 'No Number Road',
				'shipping_house_number' => '',
				'shipping_phone'        => '+49 456',
				'order_subtotal'        => '100.00',
				'discount_total'        => '5.50',
				'shipping_total'        => '4.00',
				'fee_total'             => '3.50',
				'order_total'           => '117.81',
			],
			array_map(
				static fn ( $value ): mixed => $value->value,
				( new OrderFieldValueProvider() )->get_values( $order, $fields, new ExportContext( OrderExportAdapter::ADAPTER_ID ) )
			)
		);
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$provider = new OrderFieldValueProvider();

		self::assertSame( [], $provider->get_values( new WC_Order(), new FieldCollection(), new ExportContext( 'customers' ) ) );
		self::assertSame( [], $provider->get_values( 'not-an-order', new FieldCollection(), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
	}
}
