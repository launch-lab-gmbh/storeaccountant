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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Meta\MetaField;

/**
 * Tests export field resolution and mapping.
 */
final class ExportFieldResolverTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_fields_combines_provider_additional_and_mapping_fields(): void {
		$context = new ExportContext( 'orders', 42 );

		$this->mock_field_providers(
			[
				$this->provider(
					'core',
					[
						new Field( 'order_id', 'Order ID' ),
						$this->meta_field( 'order_meta_vat', '_vat_id' ),
					]
				),
			]
		);
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_FIELD_MAPPING, true )
			->andReturn(
				json_encode(
					[
						[
							'field_id' => 'additional',
							'enabled'  => true,
							'label'    => 'Mapped Additional',
							'options'  => [ 'format' => 'custom' ],
						],
						[
							'field_id' => 'order_id',
							'enabled'  => false,
							'label'    => 'Disabled Order',
						],
						[
							'field_id' => 'missing',
							'enabled'  => true,
							'label'    => 'Missing',
						],
					]
				)
			);

		$fields = $this->resolver()->get_fields(
			$context,
			new FieldCollection( [ new Field( 'additional', 'Additional' ) ] )
		);

		self::assertSame( [ 'additional', 'order_meta_vat' ], $fields->ids() );
		self::assertSame( 'Mapped Additional', $fields->all()['additional']->label );
		self::assertSame( [ 'format' => 'custom' ], $fields->all()['additional']->options );
		self::assertSame( '_vat_id', $fields->all()['order_meta_vat']->options[ MetaField::OPTION_META_KEY ] );
	}

	public function test_get_fields_keeps_new_provider_fields_enabled_when_mapping_is_missing_them(): void {
		$context = new ExportContext( 'orders', 42 );

		$this->mock_field_providers(
			[
				$this->provider(
					'core',
					[
						new Field( 'mapped', 'Mapped' ),
						new Field( 'new_field', 'New Field' ),
					]
				),
			]
		);
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, ExportConfigurationPostType::META_FIELD_MAPPING, true )
			->andReturn(
				json_encode(
					[
						[
							'field_id' => 'mapped',
							'enabled'  => true,
							'label'    => 'Mapped Label',
						],
					]
				)
			);

		$fields = $this->resolver()->get_fields( $context );

		self::assertSame( [ 'mapped', 'new_field' ], $fields->ids() );
		self::assertSame( 'Mapped Label', $fields->all()['mapped']->label );
		self::assertSame( 'New Field', $fields->all()['new_field']->label );
	}

	public function test_get_fields_for_payload_uses_adapter_additional_fields(): void {
		$context = new ExportContext( 'orders' );
		$payload = new ExportPayload( 123, 'orders' );
		$adapter = $this->createMock( ExportAdapterInterface::class );

		$this->mock_field_providers(
			[
				$this->provider( 'core', [ new Field( 'core', 'Core' ) ] ),
			]
		);
		$adapter->expects( self::once() )
			->method( 'get_additional_fields' )
			->with( $payload, $context )
			->willReturn( new FieldCollection( [ new Field( 'adapter_extra', 'Adapter Extra' ) ] ) );

		$fields = $this->resolver()->get_fields_for_payload( $adapter, $payload, $context );

		self::assertSame( [ 'core', 'adapter_extra' ], $fields->ids() );
	}

	public function test_get_fields_moves_meta_fields_after_regular_fields(): void {
		$this->mock_field_providers(
			[
				$this->provider(
					'core',
					[
						$this->meta_field( 'meta_first', '_meta_first' ),
						new Field( 'regular', 'Regular' ),
					]
				),
			]
		);

		$fields = $this->resolver()->get_fields( new ExportContext( 'orders' ) );

		self::assertSame( [ 'regular', 'meta_first' ], $fields->ids() );
	}

	private function resolver(): ExportFieldResolver {
		return new ExportFieldResolver( new FieldProviderRegistry(), new FieldMappingRepository() );
	}

	/**
	 * @param array<int, FieldProviderInterface> $providers Field providers.
	 */
	private function mock_field_providers( array $providers ): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_provider', [] )
			->andReturn( $providers );
	}

	/**
	 * @param array<int, Field> $fields Provided fields.
	 */
	private function provider( string $id, array $fields ): FieldProviderInterface {
		return new class( $id, $fields ) implements FieldProviderInterface {
			/**
			 * @param array<int, Field> $fields Provided fields.
			 */
			public function __construct(
				private readonly string $id,
				private readonly array $fields
			) {}

			public function get_id(): string {
				return $this->id;
			}

			public function supports( ExportContext $context ): bool {
				return 'orders' === $context->export_type;
			}

			public function get_fields( ExportContext $context ): array {
				return $this->fields;
			}
		};
	}

	private function meta_field( string $id, string $meta_key ): Field {
		return new Field(
			$id,
			$meta_key,
			options: [
				MetaField::OPTION_META_KEY => $meta_key,
			]
		);
	}
}
