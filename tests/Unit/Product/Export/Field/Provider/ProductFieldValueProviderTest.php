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
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Field\Provider\ProductFieldValueProvider;
use WC_DateTime;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use function array_map;

/**
 * Tests product field value resolution.
 */
final class ProductFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		\WC_Product::$products = [
			7 => [
				'id'  => 7,
				'sku' => 'PARENT-SKU',
			],
		];

		Functions\when( 'wc_get_product' )->alias( static fn ( int $product_id ): WC_Product => new WC_Product( $product_id ) );
		Functions\when( 'get_permalink' )->alias( static fn ( int $product_id ): string => 'https://example.test/product/' . $product_id );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( string $value ): string => strip_tags( $value ) );
		Functions\when( 'wc_attribute_label' )->alias(
			static fn ( string $attribute ): string => match ( $attribute ) {
				'pa_color' => 'Color',
				'size'     => 'Size',
				'material' => 'Material',
				default    => $attribute,
			}
		);
		Functions\when( 'wc_get_product_terms' )->alias(
			static fn ( int $product_id, string $taxonomy ): array => match ( $taxonomy ) {
				'product_cat' => [ 'Clothing', 'Summer' ],
				'product_tag' => [ 'Sale' ],
				'pa_color'    => [ 'Red' ],
				default       => [],
			}
		);
	}

	protected function tearDown(): void {
		\WC_Product::$products = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_product_value_provider(): void {
		$provider = new ProductFieldValueProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( ProductFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_known_product_fields_only_for_product_exports(): void {
		$provider = new ProductFieldValueProvider();

		self::assertTrue( $provider->supports( new Field( 'price', 'price' ), new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'unknown', 'unknown' ), new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new Field( 'price', 'price' ), new ExportContext( 'orders' ) ) );
	}

	public function test_get_values_maps_product_data_and_filters_requested_fields(): void {
		$product = new WC_Product_Variation(
			[
				'id'                   => 8,
				'parent_id'            => 7,
				'name'                 => 'Summer Shirt Red',
				'slug'                 => 'summer-shirt-red',
				'sku'                  => 'CHILD-SKU',
				'type'                 => 'variation',
				'status'               => 'publish',
				'catalog_visibility'   => 'visible',
				'date_created'         => new WC_DateTime( '2026-05-01 08:15:30' ),
				'date_modified'        => new WC_DateTime( '2026-05-02 09:16:31' ),
				'menu_order'           => 3,
				'total_sales'          => 12,
				'average_rating'       => '4.5',
				'review_count'         => 2,
				'featured'             => true,
				'virtual'              => false,
				'downloadable'         => true,
				'manage_stock'         => true,
				'sold_individually'    => false,
				'regular_price'        => '29.9',
				'sale_price'           => '19.9',
				'price'                => '19.9',
				'stock_quantity'       => 5,
				'weight'               => '0.4',
				'length'               => '12',
				'width'                => '8',
				'height'               => '2',
				'tax_status'           => 'taxable',
				'tax_class'            => 'reduced-rate',
				'stock_status'         => 'instock',
				'backorders'           => 'no',
				'attributes'           => [
					new WC_Product_Attribute( 'material', false, [ 'Cotton' ] ),
					new WC_Product_Attribute( 'pa_color', true, [ 123 ] ),
				],
				'default_attributes'   => [
					'attribute_pa_color' => 'red',
					'size'               => 'M',
				],
				'variation_attributes' => [
					'attribute_pa_color' => 'red',
					'attribute_size'     => 'M',
				],
				'description'          => '<p>Long <strong>description</strong></p>',
				'short_description'    => '<p>Short copy</p>',
			]
		);
		$fields  = new FieldCollection(
			[
				new Field( 'product_id', 'product_id' ),
				new Field( 'parent_product_id', 'parent_product_id' ),
				new Field( 'sku', 'sku' ),
				new Field( 'parent_sku', 'parent_sku' ),
				new Field( 'date_created', 'date_created' ),
				new Field( 'permalink', 'permalink' ),
				new Field( 'featured', 'featured' ),
				new Field( 'virtual', 'virtual' ),
				new Field( 'price', 'price' ),
				new Field( 'stock_quantity', 'stock_quantity' ),
				new Field( 'categories', 'categories' ),
				new Field( 'tags', 'tags' ),
				new Field( 'attributes', 'attributes' ),
				new Field( 'default_attributes', 'default_attributes' ),
				new Field( 'variation_attributes', 'variation_attributes' ),
				new Field( 'description', 'description' ),
				new Field( 'short_description', 'short_description' ),
			]
		);

		self::assertSame(
			[
				'product_id'           => 8,
				'parent_product_id'    => 7,
				'sku'                  => 'CHILD-SKU',
				'parent_sku'           => 'PARENT-SKU',
				'date_created'         => '2026-05-01 08:15:30',
				'permalink'            => 'https://example.test/product/8',
				'featured'             => '1',
				'virtual'              => '0',
				'price'                => '19.90',
				'stock_quantity'       => '5.00',
				'categories'           => 'Clothing, Summer',
				'tags'                 => 'Sale',
				'attributes'           => 'Material: Cotton; Color: Red',
				'default_attributes'   => 'Color: red; Size: M',
				'variation_attributes' => 'Color: red; Size: M',
				'description'          => 'Long description',
				'short_description'    => 'Short copy',
			],
			array_map(
				static fn ( $value ): mixed => $value->value,
				( new ProductFieldValueProvider() )->get_values( $product, $fields, new ExportContext( ProductExportAdapter::ADAPTER_ID ) )
			)
		);
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$provider = new ProductFieldValueProvider();

		self::assertSame( [], $provider->get_values( new WC_Product(), new FieldCollection(), new ExportContext( 'customers' ) ) );
		self::assertSame( [], $provider->get_values( 'not-a-product', new FieldCollection(), new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
	}
}
