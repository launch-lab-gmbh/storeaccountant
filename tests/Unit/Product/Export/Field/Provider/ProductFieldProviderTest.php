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

namespace StoreAccountant\Tests\Unit\Product\Export\Field\Provider;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Type\BooleanFieldType;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Field\Provider\ProductFieldProvider;

/**
 * Tests product export field definitions.
 */
final class ProductFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_product_field_provider(): void {
		$provider = new ProductFieldProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( ProductFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_product_export_context_only(): void {
		$provider = new ProductFieldProvider();

		self::assertTrue( $provider->supports( new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_fields_returns_core_product_fields_with_types(): void {
		$fields = ( new ProductFieldProvider() )->get_fields( new ExportContext( ProductExportAdapter::ADAPTER_ID ) );

		self::assertArrayHasKey( 'product_id', $fields );
		self::assertArrayHasKey( 'parent_product_id', $fields );
		self::assertArrayHasKey( 'variation_attributes', $fields );
		self::assertInstanceOf( NumberFieldType::class, $fields['product_id']->type );
		self::assertSame( NumberFieldType::FORMAT_INTEGER, $fields['product_id']->type->format );
		self::assertInstanceOf( DateTimeFieldType::class, $fields['date_created']->type );
		self::assertInstanceOf( BooleanFieldType::class, $fields['featured']->type );
		self::assertInstanceOf( NumberFieldType::class, $fields['price']->type );
		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $fields['price']->type->format );
		self::assertCount( 40, $fields );
	}
}
