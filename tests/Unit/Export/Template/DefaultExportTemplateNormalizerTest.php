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

namespace StoreAccountant\Tests\Unit\Export\Template;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Template\DefaultExportTemplateNormalizer;

/**
 * Tests default export template normalization.
 */
final class DefaultExportTemplateNormalizerTest extends TestCase {
	public function test_normalize_returns_records_keyed_by_field_labels_in_field_order(): void {
		$dataset = new ExportDataset(
			new FieldCollection(
				[
					new Field( 'order_number', 'Order Number' ),
					new Field( 'total', 'Total' ),
					new Field( 'missing', 'Missing Value' ),
				]
			),
			[
				new ExportRecord(
					'order-1001',
					[
						new FieldValue( 'total', '42.00' ),
						new FieldValue( 'order_number', '1001' ),
					]
				),
				new ExportRecord(
					'order-1002',
					[
						new FieldValue( 'order_number', '1002' ),
						new FieldValue( 'total', null ),
					]
				),
			]
		);

		self::assertSame(
			[
				[
					'Order Number'  => '1001',
					'Total'         => '42.00',
					'Missing Value' => '',
				],
				[
					'Order Number'  => '1002',
					'Total'         => '',
					'Missing Value' => '',
				],
			],
			( new DefaultExportTemplateNormalizer() )->normalize( $dataset, new ExportPayload( 7, 'orders' ) )
		);
	}

	public function test_normalize_returns_empty_array_for_empty_dataset(): void {
		$dataset = new ExportDataset( new FieldCollection(), [] );

		self::assertSame( [], ( new DefaultExportTemplateNormalizer() )->normalize( $dataset, new ExportPayload( 7, 'orders' ) ) );
	}
}
