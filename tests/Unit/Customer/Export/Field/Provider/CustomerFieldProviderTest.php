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
use StoreAccountant\Customer\Export\Field\Provider\CustomerFieldProvider;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;

/**
 * Tests customer export field definitions.
 */
final class CustomerFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_field_provider(): void {
		$provider = new CustomerFieldProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( CustomerFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_customer_export_context_only(): void {
		$provider = new CustomerFieldProvider();

		self::assertTrue( $provider->supports( new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'orders' ) ) );
	}

	public function test_get_fields_returns_core_customer_and_address_fields(): void {
		$fields = ( new CustomerFieldProvider() )->get_fields( new ExportContext( CustomerExportAdapter::ADAPTER_ID ) );

		self::assertArrayHasKey( 'customer_id', $fields );
		self::assertArrayHasKey( 'billing_house_number', $fields );
		self::assertArrayHasKey( 'shipping_phone', $fields );
		self::assertInstanceOf( NumberFieldType::class, $fields['customer_id']->type );
		self::assertSame( NumberFieldType::FORMAT_INTEGER, $fields['customer_id']->type->format );
		self::assertInstanceOf( DateTimeFieldType::class, $fields['date_created']->type );
		self::assertSame( 'total_spent', $fields['total_spent']->label );
		self::assertCount( 35, $fields );
	}
}
