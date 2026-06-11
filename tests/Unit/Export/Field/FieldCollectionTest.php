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

namespace StoreAccountant\Tests\Unit\Export\Field;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;

/**
 * Tests export field collections.
 */
final class FieldCollectionTest extends TestCase {
	public function test_collection_indexes_fields_by_id_and_keeps_order(): void {
		$first  = new Field( 'order_number', 'Order Number' );
		$second = new Field( 'total', 'Total' );

		$collection = new FieldCollection( [ $first, $second ] );

		self::assertSame(
			[
				'order_number' => $first,
				'total'        => $second,
			],
			$collection->all()
		);
		self::assertSame( [ 'order_number', 'total' ], $collection->ids() );
		self::assertTrue( $collection->has( 'total' ) );
		self::assertFalse( $collection->has( 'missing' ) );
		self::assertCount( 2, $collection );
		self::assertSame( $collection->all(), iterator_to_array( $collection ) );
	}

	public function test_constructor_ignores_invalid_and_empty_id_fields(): void {
		$valid = new Field( 'valid', 'Valid' );

		$collection = new FieldCollection(
			[
				'not-a-field',
				new Field( '', 'Empty' ),
				$valid,
			]
		);

		self::assertSame( [ 'valid' => $valid ], $collection->all() );
	}

	public function test_constructor_keeps_last_field_when_ids_are_duplicated(): void {
		$first  = new Field( 'duplicate', 'First' );
		$second = new Field( 'duplicate', 'Second' );

		$collection = new FieldCollection( [ $first, $second ] );

		self::assertSame( [ 'duplicate' => $second ], $collection->all() );
		self::assertSame( [ 'duplicate' ], $collection->ids() );
	}

	public function test_filter_values_removes_values_without_field_definition(): void {
		$collection = new FieldCollection( [ new Field( 'total', 'Total' ) ] );
		$known      = new FieldValue( 'total', '42.00' );
		$unknown    = new FieldValue( 'missing', 'ignored' );

		self::assertSame(
			[
				'total' => $known,
			],
			$collection->filter_values(
				[
					'total'   => $known,
					'missing' => $unknown,
				]
			)
		);
	}
}
