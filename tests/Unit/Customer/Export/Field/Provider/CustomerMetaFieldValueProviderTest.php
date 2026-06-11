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
use StoreAccountant\Customer\Export\Field\Provider\CustomerMetaFieldProvider;
use StoreAccountant\Customer\Export\Field\Provider\CustomerMetaFieldValueProvider;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;
use WC_Customer;

/**
 * Tests custom customer metadata value resolution.
 */
final class CustomerMetaFieldValueProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_meta_value_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_value_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( CustomerMetaFieldValueProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_customer_meta_fields_only_for_customer_exports(): void {
		$field    = $this->field( 'favorite_color' );
		$provider = $this->provider();

		self::assertTrue( $provider->supports( $field, new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( $field, new ExportContext( 'orders' ) ) );
		self::assertFalse( $provider->supports( new Field( 'plain', 'Plain' ), new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
	}

	public function test_get_values_reads_and_formats_customer_meta(): void {
		$customer = new class(
			[
				'favorite_color' => 'Blue',
				'is_vip'         => true,
			]
		) extends WC_Customer {
			public function __construct(
				private readonly array $meta
			) {
				parent::__construct();
			}

			public function get_meta( string $key, bool $single = true ): mixed {
				return $this->meta[ $key ] ?? '';
			}
		};

		$values = $this->provider()->get_values(
			$customer,
			new FieldCollection(
				[
					$this->field( 'favorite_color' ),
					$this->field( 'is_vip' ),
					new Field( 'missing_meta_key', 'Missing' ),
				]
			),
			new ExportContext( CustomerExportAdapter::ADAPTER_ID )
		);

		self::assertSame( 'Blue', $values[ CustomerMetaFieldProvider::get_field_id( 'favorite_color' ) ]->value );
		self::assertSame( '1', $values[ CustomerMetaFieldProvider::get_field_id( 'is_vip' ) ]->value );
		self::assertArrayNotHasKey( 'missing_meta_key', $values );
	}

	public function test_get_values_returns_empty_array_for_wrong_context_or_item(): void {
		$fields = new FieldCollection( [ $this->field( 'favorite_color' ) ] );

		self::assertSame( [], $this->provider()->get_values( new \stdClass(), $fields, new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertSame( [], $this->provider()->get_values( new WC_Customer(), $fields, new ExportContext( 'orders' ) ) );
	}

	private function provider(): CustomerMetaFieldValueProvider {
		return new CustomerMetaFieldValueProvider( new MetaFieldValueFormatter() );
	}

	private function field( string $meta_key ): Field {
		return new Field(
			CustomerMetaFieldProvider::get_field_id( $meta_key ),
			$meta_key,
			options: [
				MetaField::OPTION_META_KEY => $meta_key,
			]
		);
	}
}
