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
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;

/**
 * Tests normalized export datasets.
 */
final class ExportDatasetTest extends TestCase {
	public function test_constructor_stores_fields_records_attachments_and_options(): void {
		$fields      = new FieldCollection( [ new Field( 'total', 'Total' ) ] );
		$records     = [ new ExportRecord( 'order-1', [ new FieldValue( 'total', '42.00' ) ] ) ];
		$attachments = [ 'invoice.pdf' ];
		$options     = [ 'format' => 'csv' ];

		$dataset = new ExportDataset( $fields, $records, $attachments, $options );

		self::assertSame( $fields, $dataset->fields );
		self::assertSame( $records, $dataset->records );
		self::assertSame( $attachments, $dataset->attachments );
		self::assertSame( $options, $dataset->options );
	}

	public function test_empty_dataset_is_valid(): void {
		$fields = new FieldCollection();

		$dataset = new ExportDataset( $fields, [] );

		self::assertSame( $fields, $dataset->fields );
		self::assertSame( [], $dataset->records );
		self::assertSame( [], $dataset->attachments );
		self::assertSame( [], $dataset->options );
	}
}
