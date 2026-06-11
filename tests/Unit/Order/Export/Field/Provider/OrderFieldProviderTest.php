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
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Field\Provider\OrderFieldProvider;

/**
 * Tests order export field definitions.
 */
final class OrderFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_field_provider(): void {
		$provider = new OrderFieldProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( OrderFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_order_export_context_only(): void {
		$provider = new OrderFieldProvider();

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_fields_returns_core_order_address_and_amount_fields(): void {
		$fields = ( new OrderFieldProvider() )->get_fields( new ExportContext( OrderExportAdapter::ADAPTER_ID ) );

		self::assertArrayHasKey( 'order_id', $fields );
		self::assertArrayHasKey( 'shipping_house_number', $fields );
		self::assertArrayHasKey( 'billing_email', $fields );
		self::assertArrayHasKey( 'fee_total', $fields );
		self::assertInstanceOf( NumberFieldType::class, $fields['order_id']->type );
		self::assertSame( NumberFieldType::FORMAT_INTEGER, $fields['order_id']->type->format );
		self::assertInstanceOf( DateTimeFieldType::class, $fields['order_date']->type );
		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $fields['order_total']->type->format );
		self::assertCount( 38, $fields );
	}
}
