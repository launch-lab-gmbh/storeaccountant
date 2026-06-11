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

namespace StoreAccountant\Tests\Unit\Export\Dataset;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Field\FieldValue;

/**
 * Tests export dataset records.
 */
final class ExportRecordTest extends TestCase {
	public function test_constructor_stores_record_data(): void {
		$values = [
			new FieldValue( 'order_number', '1001' ),
			new FieldValue( 'total', '42.00' ),
		];

		$record = new ExportRecord( 'order-1001', $values, [ 'source' => 'orders' ] );

		self::assertSame( 'order-1001', $record->id );
		self::assertSame( $values, $record->values );
		self::assertSame( [ 'source' => 'orders' ], $record->options );
	}

	public function test_get_value_returns_first_matching_field_value(): void {
		$record = new ExportRecord(
			'order-1001',
			[
				new FieldValue( 'total', 'first' ),
				new FieldValue( 'total', 'second' ),
			]
		);

		self::assertSame( 'first', $record->get_value( 'total' ) );
	}

	public function test_get_value_returns_null_for_unknown_field(): void {
		$record = new ExportRecord( 'order-1001', [ new FieldValue( 'total', '42.00' ) ] );

		self::assertNull( $record->get_value( 'missing' ) );
	}
}
