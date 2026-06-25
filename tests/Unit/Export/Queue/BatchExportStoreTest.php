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

namespace StoreAccountant\Tests\Unit\Export\Queue;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Export\Queue\BatchExportStore;
use WP_Error;

/**
 * Tests temporary queued export batch storage.
 */
final class BatchExportStoreTest extends TestCase {
	private string $tmp_dir;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->tmp_dir = sys_get_temp_dir() . '/storeaccountant-batch-test-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->tmp_dir, 0777, true );

		$GLOBALS['wp_filesystem'] = new class() {
			public function put_contents( string $path, string $contents ): bool {
				return false !== file_put_contents( $path, $contents );
			}

			public function get_contents( string $path ): string|false {
				return file_get_contents( $path );
			}

			public function rmdir( string $path ): bool {
				return @rmdir( $path );
			}

			public function delete( string $path, bool $recursive = false, string|false $type = false ): bool {
				if ( ! is_dir( $path ) ) {
					return false;
				}

				$items = scandir( $path );

				if ( false === $items ) {
					return false;
				}

				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$item_path = $path . DIRECTORY_SEPARATOR . $item;

					if ( is_dir( $item_path ) ) {
						if ( ! $recursive || ! $this->delete( $item_path, true, 'd' ) ) {
							return false;
						}

						continue;
					}

					if ( is_file( $item_path ) && ! unlink( $item_path ) ) {
						return false;
					}
				}

				return rmdir( $path );
			}
		};

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wp_upload_dir' )->alias( fn (): array => [ 'basedir' => $this->tmp_dir ] );
		Functions\when( 'get_temp_dir' )->alias( fn (): string => $this->tmp_dir );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true ) );
		Functions\when( 'wp_is_writable' )->alias( static fn ( string $path ): bool => is_writable( $path ) );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'WP_Filesystem' )->alias( static fn (): bool => true );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->tmp_dir );
		unset( $GLOBALS['wp_filesystem'] );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_save_batch_persists_dataset_under_export_id_and_batch_index(): void {
		$result = ( new BatchExportStore() )->save_batch( 123, 2, $this->dataset( 'second', 'B' ) );

		self::assertTrue( $result );
		$path = $this->tmp_dir . '/storeaccountant/tmp/exports/123/batch-00002.dat';
		$attachments_path = $this->tmp_dir . '/storeaccountant/tmp/exports/123/batch-00002-attachments.dat';

		self::assertFileExists( $path );
		self::assertFileExists( $attachments_path );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/index.html' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/.htaccess' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/index.html' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/.htaccess' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/exports/index.html' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/exports/.htaccess' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/exports/123/index.html' );
		self::assertFileExists( $this->tmp_dir . '/storeaccountant/tmp/exports/123/.htaccess' );

		$stored = json_decode( (string) file_get_contents( $path ), true );
		$stored_attachments = json_decode( (string) file_get_contents( $attachments_path ), true );

		self::assertSame( 'total', $stored['fields'][0]['id'] );
		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $stored['fields'][0]['type'] );
		self::assertSame( 'second', $stored['records'][0]['id'] );
		self::assertSame( 'B', $stored['records'][0]['values'][0]['value'] );
		self::assertSame( 'attachments/empty.txt', $stored_attachments[0]['internal_path'] );
	}

	public function test_load_dataset_reads_fragments_in_order_and_reconstructs_dataset(): void {
		$store = new BatchExportStore();

		self::assertTrue( $store->save_batch( 123, 2, $this->dataset( 'second', 'B' ) ) );
		self::assertTrue( $store->save_batch( 123, 1, $this->dataset( 'first', 'A' ) ) );

		$dataset = $store->load_dataset( 123 );

		self::assertInstanceOf( ExportDataset::class, $dataset );
		self::assertSame( [ 'total' ], $dataset->fields->ids() );
		self::assertSame(
			[ 'first', 'second' ],
			array_map(
				static fn ( ExportRecord $record ): string => (string) $record->id,
				iterator_to_array( $dataset->records )
			)
		);
	}

	public function test_count_and_load_attachments_reads_slices_without_records(): void {
		$store = new BatchExportStore();

		self::assertTrue( $store->save_batch( 123, 1, $this->dataset( 'first', 'A' ) ) );
		self::assertTrue( $store->save_batch( 123, 2, $this->dataset( 'second', 'B' ) ) );

		self::assertSame( 2, $store->count_attachments( 123 ) );

		$attachments = iterator_to_array( $store->load_attachments( 123, 1, 1 ) );

		self::assertCount( 1, $attachments );
		self::assertInstanceOf( ExportAttachment::class, $attachments[0] );
		self::assertSame( 'attachments/empty.txt', $attachments[0]->internal_path );
		self::assertIsResource( $attachments[0]->stream );
		fclose( $attachments[0]->stream );
	}

	public function test_item_id_snapshot_persists_deduplicates_and_reads_slices(): void {
		$store = new BatchExportStore();

		self::assertTrue( $store->save_item_ids( 123, [ '7', 8, '7', '', '9' ] ) );
		self::assertTrue( $store->has_item_id_snapshot( 123 ) );
		self::assertSame( 3, $store->count_item_ids( 123 ) );
		self::assertSame( [ '8', '9' ], $store->load_item_ids( 123, 1, 2 ) );
	}

	public function test_load_dataset_returns_error_for_missing_or_invalid_fragments(): void {
		$store = new BatchExportStore();

		self::assertInstanceOf( WP_Error::class, $store->load_dataset( 404 ) );

		$directory = $this->tmp_dir . '/storeaccountant/tmp/exports/123';
		mkdir( $directory, 0777, true );
		file_put_contents( $directory . '/batch-00001.dat', 'not-json' );

		$error = $store->load_dataset( 123 );

		self::assertInstanceOf( WP_Error::class, $error );
		self::assertSame( 'storeaccountant_export_batch_invalid', $error->get_error_code() );
	}

	public function test_delete_export_removes_all_batch_files_for_export(): void {
		$store = new BatchExportStore();
		self::assertTrue( $store->save_batch( 123, 1, $this->dataset( 'first', 'A' ) ) );

		$directory = $this->tmp_dir . '/storeaccountant/tmp/exports/123';
		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/nested/generated-list.dat', 'temporary export list' );
		self::assertDirectoryExists( $directory );

		$store->delete_export( 123 );

		self::assertDirectoryDoesNotExist( $directory );
	}

	private function dataset( string $record_id, string $value ): ExportDataset {
		return new ExportDataset(
			new FieldCollection(
				[
					new Field( 'total', 'Total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
				]
			),
			[
				new ExportRecord(
					$record_id,
					[
						new FieldValue( 'total', $value ),
					]
				),
			],
			[
				new ExportAttachment( fopen( 'php://temp', 'rb+' ), 'empty.txt', 'text/plain', 'attachments/empty.txt' ),
			],
			[ 'source' => 'unit-test' ]
		);
	}

	private function remove_directory( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$items = scandir( $path );

		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$item_path = $path . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $item_path ) ) {
				$this->remove_directory( $item_path );
				continue;
			}

			unlink( $item_path );
		}

		rmdir( $path );
	}
}
