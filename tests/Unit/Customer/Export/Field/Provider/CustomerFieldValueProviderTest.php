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

namespace StoreAccountant\Tests\Unit\Customer\Export\Field\Provider;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Field\Provider\CustomerFieldValueProvider;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use WC_Customer;
use WC_DateTime;
use function array_map;

/**
 * Tests customer field value resolution.
 */
final class CustomerFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_value_provider(): void {
		$provider = new CustomerFieldValueProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( CustomerFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_known_customer_fields_only_for_customer_exports(): void {
		$provider = new CustomerFieldValueProvider();

		self::assertTrue( $provider->supports( new Field( 'email', 'email' ), new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'unknown', 'unknown' ), new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'email', 'email' ), new ExportContext( 'orders' ) ) );
	}

	public function test_get_values_maps_customer_data_and_filters_requested_fields(): void {
		$customer = new WC_Customer(
			[
				'id'                 => 42,
				'username'           => 'jane',
				'email'              => 'jane@example.test',
				'display_name'       => 'Jane Customer',
				'date_created'       => new WC_DateTime( '2026-05-01 08:15:30' ),
				'date_modified'      => new WC_DateTime( '2026-05-02 09:16:31' ),
				'order_count'        => 3,
				'total_spent'        => '123.4',
				'billing_address_1'  => 'Main Street 12a',
				'shipping_address_1' => 'Warehouse Road 5-7',
				'shipping_phone'     => '+49 123',
			]
		);
		$fields   = new FieldCollection(
			[
				new Field( 'customer_id', 'customer_id' ),
				new Field( 'display_name', 'display_name' ),
				new Field( 'date_created', 'date_created' ),
				new Field( 'total_spent', 'total_spent' ),
				new Field( 'billing_street', 'billing_street' ),
				new Field( 'billing_house_number', 'billing_house_number' ),
				new Field( 'shipping_street', 'shipping_street' ),
				new Field( 'shipping_house_number', 'shipping_house_number' ),
				new Field( 'shipping_phone', 'shipping_phone' ),
			]
		);

		self::assertSame(
			[
				'customer_id'           => 42,
				'display_name'          => 'Jane Customer',
				'date_created'          => '2026-05-01 08:15:30',
				'total_spent'           => '123.40',
				'billing_street'        => 'Main Street',
				'billing_house_number'  => '12a',
				'shipping_street'       => 'Warehouse Road',
				'shipping_house_number' => '5-7',
				'shipping_phone'        => '+49 123',
			],
			array_map(
				static fn ( $value ): mixed => $value->value,
				( new CustomerFieldValueProvider() )->get_values( $customer, $fields, new ExportContext( CustomerExportAdapter::ADAPTER_ID ) )
			)
		);
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$provider = new CustomerFieldValueProvider();

		self::assertSame( [], $provider->get_values( new WC_Customer(), new FieldCollection(), new ExportContext( 'orders' ) ) );
		self::assertSame( [], $provider->get_values( 'not-a-customer', new FieldCollection(), new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
	}
}
