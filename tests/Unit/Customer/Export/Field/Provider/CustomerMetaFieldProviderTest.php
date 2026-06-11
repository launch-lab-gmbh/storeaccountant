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
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;

/**
 * Tests custom customer metadata field definitions.
 */
final class CustomerMetaFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_customer_meta_field_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( CustomerMetaFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_customer_export_context_only(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports( new ExportContext( CustomerExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'orders' ) ) );
	}

	public function test_get_fields_collects_customer_meta_fields_and_ignores_reserved_keys(): void {
		$fields = $this->provider()->get_fields(
			new ExportContext(
				CustomerExportAdapter::ADAPTER_ID,
				items: [
					$this->item_with_meta(
						[
							'billing_first_name' => 'Reserved',
							'favorite_color'     => 'Blue',
							'vat_id'             => 'DE123',
						]
					),
				]
			)
		);

		$favorite_color_id = CustomerMetaFieldProvider::get_field_id( 'favorite_color' );
		$vat_id            = CustomerMetaFieldProvider::get_field_id( 'vat_id' );

		self::assertSame( [ $favorite_color_id, $vat_id ], array_keys( $fields ) );
		self::assertSame( 'favorite_color', $fields[ $favorite_color_id ]->label );
		self::assertSame( 'favorite_color', $fields[ $favorite_color_id ]->options[ MetaField::OPTION_META_KEY ] );
	}

	public function test_get_field_id_uses_customer_meta_prefix(): void {
		self::assertSame(
			MetaField::get_field_id( CustomerMetaFieldProvider::FIELD_ID_PREFIX, 'vat_id' ),
			CustomerMetaFieldProvider::get_field_id( 'vat_id' )
		);
	}

	private function provider(): CustomerMetaFieldProvider {
		return new CustomerMetaFieldProvider( new MetaFieldCollector() );
	}

	/**
	 * @param array<string, mixed> $meta Metadata keyed by meta key.
	 */
	private function item_with_meta( array $meta ): object {
		return new class( $meta ) {
			public function __construct(
				private readonly array $meta
			) {}

			public function get_meta_data(): array {
				return array_map(
					static fn ( string $key, mixed $value ): object => new class( $key, $value ) {
						public function __construct(
							private readonly string $key,
							private readonly mixed $value
						) {}

						public function get_data(): array {
							return [
								'key'   => $this->key,
								'value' => $this->value,
							];
						}
					},
					array_keys( $this->meta ),
					$this->meta
				);
			}
		};
	}
}
