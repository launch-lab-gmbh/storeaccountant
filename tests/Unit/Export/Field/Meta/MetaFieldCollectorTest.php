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

namespace StoreAccountant\Tests\Unit\Export\Field\Meta;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;

/**
 * Tests metadata field collection from export context items.
 */
final class MetaFieldCollectorTest extends TestCase {
	public function test_get_fields_collects_sorted_exportable_meta_fields(): void {
		$context = new ExportContext(
			'orders',
			items: [
				$this->item_with_meta(
					[
						'_zeta'  => 'last',
						'_alpha' => 'first',
					]
				),
				$this->item_with_meta(
					[
						'_middle' => null,
					]
				),
			]
		);

		$fields = ( new MetaFieldCollector() )->get_fields( $context, 'order_meta_', [] );

		self::assertSame(
			[
				MetaField::get_field_id( 'order_meta_', '_alpha' ),
				MetaField::get_field_id( 'order_meta_', '_middle' ),
				MetaField::get_field_id( 'order_meta_', '_zeta' ),
			],
			array_keys( $fields )
		);
		self::assertSame( '_alpha', $fields[ MetaField::get_field_id( 'order_meta_', '_alpha' ) ]->label );
		self::assertSame(
			'_middle',
			$fields[ MetaField::get_field_id( 'order_meta_', '_middle' ) ]->options[ MetaField::OPTION_META_KEY ]
		);
	}

	public function test_get_fields_ignores_reserved_duplicate_and_non_scalar_meta(): void {
		$context = new ExportContext(
			'orders',
			items: [
				$this->item_with_meta(
					[
						'_reserved'  => 'already dedicated',
						'_duplicate' => 'first',
						'_array'     => [ 'not exportable' ],
					]
				),
				$this->item_with_meta(
					[
						'_duplicate' => 'second',
						'_valid'     => true,
					]
				),
				'not-an-object',
				new \stdClass(),
			]
		);

		$fields = ( new MetaFieldCollector() )->get_fields( $context, 'order_meta_', [ '_reserved' ] );

		self::assertSame(
			[
				MetaField::get_field_id( 'order_meta_', '_duplicate' ),
				MetaField::get_field_id( 'order_meta_', '_valid' ),
			],
			array_keys( $fields )
		);
	}

	public function test_get_fields_ignores_meta_entries_without_valid_key(): void {
		$context = new ExportContext(
			'orders',
			items: [
				$this->item_with_raw_meta(
					[
						[
							'key'   => '',
							'value' => 'empty key',
						],
						[ 'value' => 'missing key' ],
						[
							'key'   => '_valid',
							'value' => 'yes',
						],
					]
				),
			]
		);

		$fields = ( new MetaFieldCollector() )->get_fields( $context, 'order_meta_', [] );

		self::assertSame( [ MetaField::get_field_id( 'order_meta_', '_valid' ) ], array_keys( $fields ) );
	}

	/**
	 * Builds an item exposing WooCommerce-like metadata objects.
	 *
	 * @param array<string, mixed> $meta Metadata keyed by meta key.
	 */
	private function item_with_meta( array $meta ): object {
		$raw_meta = [];

		foreach ( $meta as $key => $value ) {
			$raw_meta[] = [
				'key'   => $key,
				'value' => $value,
			];
		}

		return $this->item_with_raw_meta( $raw_meta );
	}

	/**
	 * Builds an item exposing raw metadata rows.
	 *
	 * @param array<int, array<string, mixed>> $raw_meta Raw metadata rows.
	 */
	private function item_with_raw_meta( array $raw_meta ): object {
		return new class( $raw_meta ) {
			/**
			 * @param array<int, array<string, mixed>> $raw_meta Raw metadata rows.
			 */
			public function __construct(
				private array $raw_meta
			) {}

			/**
			 * Gets metadata objects.
			 *
			 * @return array<int, object>
			 */
			public function get_meta_data(): array {
				return array_map(
					static fn ( array $data ): object => new class( $data ) {
						/**
						 * @param array<string, mixed> $data Metadata data.
						 */
						public function __construct(
							private array $data
						) {}

						/**
						 * Gets metadata row data.
						 *
						 * @return array<string, mixed>
						 */
						public function get_data(): array {
							return $this->data;
						}
					},
					$this->raw_meta
				);
			}
		};
	}
}
