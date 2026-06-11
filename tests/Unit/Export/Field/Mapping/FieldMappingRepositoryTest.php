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

namespace StoreAccountant\Tests\Unit\Export\Field\Mapping;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Type\NumberFieldType;

/**
 * Tests persisted export field mappings.
 */
final class FieldMappingRepositoryTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key, bool $single = false ): mixed => $this->meta[ $key ] ?? ''
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( strip_tags( $value ) ) );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_mapped_fields_merges_mapping_with_current_fields_and_ignores_unknown_or_disabled(): void {
		$this->store_mapping(
			[
				[
					'field_id' => 'total',
					'enabled'  => true,
					'label'    => 'Mapped Total',
					'options'  => [ 'format' => 'cents' ],
				],
				[
					'field_id' => 'disabled',
					'enabled'  => false,
					'label'    => 'Disabled',
				],
				[
					'field_id' => 'unknown',
					'enabled'  => true,
					'label'    => 'Unknown',
				],
			]
		);

		$fields = ( new FieldMappingRepository() )->get_mapped_fields( 42, $this->available_fields() );

		self::assertSame( [ 'total', 'new_field', 'order_meta_vat' ], $fields->ids() );
		self::assertSame( 'Mapped Total', $fields->all()['total']->label );
		self::assertSame(
			[
				'source' => 'provider',
				'format' => 'cents',
			],
			$fields->all()['total']->options
		);
		self::assertArrayNotHasKey( 'disabled', $fields->all() );
	}

	public function test_get_form_items_uses_stored_label_enabled_state_options_and_moves_meta_to_end(): void {
		$this->store_mapping(
			[
				[
					'field_id' => 'order_meta_vat',
					'enabled'  => true,
					'label'    => 'VAT',
					'options'  => [ 'x' => '1' ],
				],
				[
					'field_id' => 'total',
					'enabled'  => false,
					'label'    => '',
					'options'  => [ 'format' => 'amount' ],
				],
			]
		);

		$items = ( new FieldMappingRepository() )->get_form_items( 42, $this->available_fields() );

		self::assertSame( [ 'total', 'new_field', 'disabled', 'order_meta_vat' ], array_column( $items, 'field_id' ) );
		self::assertFalse( $items[0]['enabled'] );
		self::assertSame( 'Total', $items[0]['label'] );
		self::assertSame(
			[
				'source' => 'provider',
				'format' => 'amount',
			],
			$items[0]['options']
		);
		self::assertTrue( $items[1]['enabled'] );
		self::assertSame( 'New Field', $items[1]['label'] );
		self::assertSame( 'VAT', $items[3]['label'] );
	}

	public function test_sanitize_from_request_validates_order_checkboxes_labels_and_options(): void {
		$mapping = ( new FieldMappingRepository() )->sanitize_from_request(
			[
				'storeaccountant_field_mapping_order' => [ 'new_field', 'total', 'missing', 'new_field' ],
				'storeaccountant_field_mapping'       => [
					'new_field' => [
						'enabled' => '1',
						'label'   => ' <b>Custom Label</b> ',
						'options' => [
							'Format Key' => ' value ',
							'nested'     => [ 'ignored' ],
						],
					],
					'total'     => [
						'label' => '',
					],
				],
			],
			$this->available_fields()
		);

		self::assertSame( [ 'new_field', 'total', 'disabled', 'order_meta_vat' ], array_column( $mapping, 'field_id' ) );
		self::assertTrue( $mapping[0]['enabled'] );
		self::assertSame( 'Custom Label', $mapping[0]['label'] );
		self::assertSame( [ 'formatkey' => 'value' ], $mapping[0]['options'] );
		self::assertFalse( $mapping[1]['enabled'] );
		self::assertSame( 'Total', $mapping[1]['label'] );
	}

	public function test_save_writes_json_meta(): void {
		$mapping = [
			[
				'field_id' => 'total',
				'enabled'  => true,
				'label'    => 'Total',
				'options'  => [],
			],
		];

		( new FieldMappingRepository() )->save( 42, $mapping );

		self::assertSame( json_encode( $mapping ), $this->meta[ ExportConfigurationPostType::META_FIELD_MAPPING ] );
	}

	public function test_refresh_matching_fields_replaces_only_matching_items(): void {
		$this->store_mapping(
			[
				[
					'field_id' => 'total',
					'enabled'  => false,
					'label'    => 'Old Total',
					'options'  => [],
				],
				[
					'field_id' => 'new_field',
					'enabled'  => true,
					'label'    => 'Keep Me',
					'options'  => [],
				],
			]
		);

		( new FieldMappingRepository() )->refresh_matching_fields(
			42,
			$this->available_fields(),
			static fn ( string $field_id ): bool => 'total' === $field_id || str_starts_with( $field_id, 'order_meta_' )
		);

		$stored = json_decode( $this->meta[ ExportConfigurationPostType::META_FIELD_MAPPING ], true );

		self::assertSame( [ 'total', 'order_meta_vat', 'new_field' ], array_column( $stored, 'field_id' ) );
		self::assertTrue( $stored[0]['enabled'] );
		self::assertSame( 'Total', $stored[0]['label'] );
		self::assertSame( 'Keep Me', $stored[2]['label'] );
	}

	private function available_fields(): FieldCollection {
		return new FieldCollection(
			[
				new Field( 'total', 'Total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ), [ 'source' => 'provider' ] ),
				new Field( 'new_field', 'New Field' ),
				new Field( 'disabled', 'Disabled' ),
				new Field( 'order_meta_vat', 'VAT ID', 'string', [ MetaField::OPTION_META_KEY => '_vat_id' ] ),
			]
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $mapping Mapping items.
	 */
	private function store_mapping( array $mapping ): void {
		$this->meta[ ExportConfigurationPostType::META_FIELD_MAPPING ] = json_encode( $mapping );
	}
}
