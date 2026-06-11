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
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldProvider;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldValueProvider;
use WC_Order;

/**
 * Tests custom order metadata value resolution.
 */
final class OrderMetaFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_meta_value_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( OrderMetaFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_order_meta_fields_only_for_order_exports(): void {
		$field    = $this->field( '_external_id' );
		$provider = $this->provider();

		self::assertTrue( $provider->supports( $field, new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( $field, new ExportContext( 'customers' ) ) );
		self::assertFalse( $provider->supports( new Field( 'plain', 'Plain' ), new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
	}

	public function test_get_values_reads_and_formats_order_meta(): void {
		$order = new WC_Order(
			[
				'_external_id' => 'EXT-1',
				'_flag'        => true,
			]
		);

		$values = $this->provider()->get_values(
			$order,
			new FieldCollection(
				[
					$this->field( '_external_id' ),
					$this->field( '_flag' ),
					new Field( 'missing_meta_key', 'Missing' ),
				]
			),
			new ExportContext( OrderExportAdapter::ADAPTER_ID )
		);

		self::assertSame( 'EXT-1', $values[ OrderMetaFieldProvider::get_field_id( '_external_id' ) ]->value );
		self::assertSame( '1', $values[ OrderMetaFieldProvider::get_field_id( '_flag' ) ]->value );
		self::assertArrayNotHasKey( 'missing_meta_key', $values );
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$fields = new FieldCollection( [ $this->field( '_external_id' ) ] );

		self::assertSame( [], $this->provider()->get_values( new \stdClass(), $fields, new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( [], $this->provider()->get_values( new WC_Order(), $fields, new ExportContext( 'customers' ) ) );
	}

	private function provider(): OrderMetaFieldValueProvider {
		return new OrderMetaFieldValueProvider( new MetaFieldValueFormatter() );
	}

	private function field( string $meta_key ): Field {
		return new Field(
			OrderMetaFieldProvider::get_field_id( $meta_key ),
			$meta_key,
			options: [
				MetaField::OPTION_META_KEY => $meta_key,
			]
		);
	}
}
