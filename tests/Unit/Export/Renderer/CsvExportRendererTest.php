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

namespace StoreAccountant\Tests\Unit\Export\Renderer;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Renderer\CsvExportRenderer;

/**
 * Tests CSV export rendering.
 */
final class CsvExportRendererTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_renderer_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_renderer', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new CsvExportRenderer() )->register();

		self::assertTrue( true );
	}

	public function test_getters_return_stable_renderer_metadata(): void {
		$renderer = new CsvExportRenderer();

		self::assertSame( CsvExportRenderer::RENDERER_ID, $renderer->get_id() );
		self::assertSame( 'csv', $renderer->get_file_extension() );
		self::assertSame( 'text/csv', $renderer->get_mime_type() );
	}

	public function test_render_writes_csv_header_and_rows_in_field_order(): void {
		$file_path = tempnam( sys_get_temp_dir(), 'storeaccountant-csv-' );

		self::assertIsString( $file_path );

		Functions\expect( 'wp_tempnam' )
			->once()
			->with( 'storeaccountant-export-42.csv' )
			->andReturn( $file_path );

		$dataset = new ExportDataset(
			new FieldCollection(
				[
					new Field( 'order_number', 'Order Number' ),
					new Field( 'note', 'Note' ),
					new Field( 'missing', 'Missing' ),
				]
			),
			[
				new ExportRecord(
					'order-1',
					[
						new FieldValue( 'note', 'Hello, "CSV"' ),
						new FieldValue( 'order_number', '1001' ),
					]
				),
			]
		);

		$artifact = ( new CsvExportRenderer() )->render( $dataset, new ExportPayload( 42, 'orders' ) );

		self::assertSame( $file_path, $artifact->source_path );
		self::assertSame( 'csv', $artifact->file_extension );
		self::assertSame( 'text/csv', $artifact->mime_type );
		$contents = (string) file_get_contents( $file_path );

		self::assertStringContainsString( '"Order Number",Note,Missing', $contents );
		self::assertStringContainsString( '1001,"Hello, ""CSV""",', $contents );

		unlink( $file_path );
	}
}
