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
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Field\Provider\ProductMetaFieldProvider;
use StoreAccountant\Product\Export\Field\Provider\ProductMetaFieldValueProvider;
use WC_Product;

/**
 * Tests custom product metadata value resolution.
 */
final class ProductMetaFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_product_meta_value_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( ProductMetaFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_product_meta_fields_only_for_product_exports(): void {
		$field    = $this->field( 'brand' );
		$provider = $this->provider();

		self::assertTrue( $provider->supports( $field, new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( $field, new ExportContext( 'orders' ) ) );
		self::assertFalse( $provider->supports( new Field( 'plain', 'Plain' ), new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
	}

	public function test_get_values_reads_and_formats_product_meta(): void {
		$product = new WC_Product(
			[
				'meta' => [
					'brand'     => 'LaunchLab',
					'is_custom' => true,
				],
			]
		);

		$values = $this->provider()->get_values(
			$product,
			new FieldCollection(
				[
					$this->field( 'brand' ),
					$this->field( 'is_custom' ),
					new Field( 'missing_meta_key', 'Missing' ),
				]
			),
			new ExportContext( ProductExportAdapter::ADAPTER_ID )
		);

		self::assertSame( 'LaunchLab', $values[ ProductMetaFieldProvider::get_field_id( 'brand' ) ]->value );
		self::assertSame( '1', $values[ ProductMetaFieldProvider::get_field_id( 'is_custom' ) ]->value );
		self::assertArrayNotHasKey( 'missing_meta_key', $values );
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$fields = new FieldCollection( [ $this->field( 'brand' ) ] );

		self::assertSame( [], $this->provider()->get_values( new \stdClass(), $fields, new ExportContext( ProductExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( [], $this->provider()->get_values( new WC_Product(), $fields, new ExportContext( 'orders' ) ) );
	}

	private function provider(): ProductMetaFieldValueProvider {
		return new ProductMetaFieldValueProvider( new MetaFieldValueFormatter() );
	}

	private function field( string $meta_key ): Field {
		return new Field(
			ProductMetaFieldProvider::get_field_id( $meta_key ),
			$meta_key,
			options: [
				MetaField::OPTION_META_KEY => $meta_key,
			]
		);
	}
}
