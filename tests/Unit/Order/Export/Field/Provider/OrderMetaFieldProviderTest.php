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
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldProvider;

/**
 * Tests custom order metadata field definitions.
 */
final class OrderMetaFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_order_meta_field_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertSame( OrderMetaFieldProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_order_export_context_only(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports( new ExportContext( OrderExportAdapter::ADAPTER_ID ) ) );
		self::assertFalse( $provider->supports( new ExportContext( 'customers' ) ) );
	}

	public function test_get_fields_collects_order_meta_fields_and_ignores_reserved_keys(): void {
		$fields = $this->provider()->get_fields(
			new ExportContext(
				OrderExportAdapter::ADAPTER_ID,
				items: [
					$this->item_with_meta(
						[
							'_order_total' => 'Reserved',
							'_source_id'   => 'ABC',
							'_external_id' => 'EXT-1',
						]
					),
				]
			)
		);

		$source_id   = OrderMetaFieldProvider::get_field_id( '_source_id' );
		$external_id = OrderMetaFieldProvider::get_field_id( '_external_id' );

		self::assertSame( [ $external_id, $source_id ], array_keys( $fields ) );
		self::assertSame( '_external_id', $fields[ $external_id ]->label );
		self::assertSame( '_external_id', $fields[ $external_id ]->options[ MetaField::OPTION_META_KEY ] );
	}

	public function test_get_field_id_uses_order_meta_prefix(): void {
		self::assertSame(
			MetaField::get_field_id( OrderMetaFieldProvider::FIELD_ID_PREFIX, '_external_id' ),
			OrderMetaFieldProvider::get_field_id( '_external_id' )
		);
	}

	private function provider(): OrderMetaFieldProvider {
		return new OrderMetaFieldProvider( new MetaFieldCollector() );
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
