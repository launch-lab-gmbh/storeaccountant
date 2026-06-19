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
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Field\Provider\ProductMetaFieldProvider;
use WC_Product;

/**
 * Tests custom product metadata field definitions.
 */
final class ProductMetaFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_product_meta_field_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( ProductMetaFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_product_export_context_only(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports( new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'orders' ) ) );
	}

	public function test_get_fields_collects_product_meta_fields_and_ignores_reserved_keys(): void {
		$fields = $this->provider()->get_fields(
			new ExportContext(
				ProductExportAdapter::ADAPTER_ID,
				items: [
					new WC_Product(
						[
							'meta' => [
								'_sku'        => 'Reserved',
								'brand'       => 'LaunchLab',
								'external_id' => 'EXT-1',
							],
						]
					),
				]
			)
		);

		$brand       = ProductMetaFieldProvider::get_field_id( 'brand' );
		$external_id = ProductMetaFieldProvider::get_field_id( 'external_id' );

		self::assertSame( [ $brand, $external_id ], array_keys( $fields ) );
		self::assertSame( 'brand', $fields[ $brand ]->label );
		self::assertSame( 'brand', $fields[ $brand ]->options[ MetaField::OPTION_META_KEY ] );
	}

	public function test_get_field_id_uses_product_meta_prefix(): void {
		self::assertSame(
			MetaField::get_field_id( ProductMetaFieldProvider::FIELD_ID_PREFIX, 'external_id' ),
			ProductMetaFieldProvider::get_field_id( 'external_id' )
		);
	}

	private function provider(): ProductMetaFieldProvider {
		return new ProductMetaFieldProvider( new MetaFieldCollector() );
	}
}
