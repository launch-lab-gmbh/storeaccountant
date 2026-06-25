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

namespace StoreAccountant\Tests\Unit\Storage\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ZipArchive;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\StorageFileConfiguration;
use WP_Error;

/**
 * Tests local storage adapter file lifecycle.
 */
final class LocalStorageAdapterTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->root = sys_get_temp_dir() . '/storeaccountant-local-storage-' . uniqid( '', true );

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'sanitize_file_name' )->alias(
			static fn ( string $value ): string => str_replace( ' ', '-', $value )
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( str_replace( [ ' ', '_' ], '-', $value ) )
		);
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true ) );
		Functions\when( 'wp_is_writable' )->alias( static fn ( string $path ): bool => is_writable( $path ) );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			eval( 'namespace { function WP_Filesystem(): bool { return true; } }' );
		}

		$GLOBALS['wp_filesystem'] = new class() {
			public function put_contents( string $path, string $contents ): bool {
				if ( ! is_dir( dirname( $path ) ) ) {
					mkdir( dirname( $path ), 0777, true );
				}

				return false !== file_put_contents( $path, $contents );
			}

			public function rmdir( string $path ): bool {
				return is_dir( $path ) && rmdir( $path );
			}
		};
	}

	protected function tearDown(): void {
		$this->delete_directory( $this->root );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_local_storage_adapter_filter(): void {
		$adapter = $this->adapter();

		Monkey\Filters\expectAdded( 'storeaccountant_storage_adapter' )->once();

		$adapter->register();

		self::assertSame( LocalStorageAdapter::ENGINE_ID, $adapter->get_id() );
	}

	public function test_ensure_creates_root_and_protection_files(): void {
		self::assertTrue( $this->adapter()->ensure() );
		self::assertFileExists( $this->root . '/index.html' );
		self::assertFileExists( $this->root . '/.htaccess' );
		self::assertSame( 'deny from all', file_get_contents( $this->root . '/.htaccess' ) );
	}

	public function test_persist_get_file_and_delete_file_round_trip_zip_archive(): void {
		$source = $this->root . '-source.csv';
		file_put_contents( $source, 'order_id,total' );

		$result = $this->adapter()->persist(
			new StorageFileConfiguration(
				'exports/token.zip',
				$source,
				'token.csv',
				'token.csv',
				[],
				'text/csv'
			)
		);

		self::assertSame( 'exports/token.zip', $result );
		self::assertFileExists( $this->root . '/exports/index.html' );
		self::assertFileExists( $this->root . '/exports/.htaccess' );
		self::assertTrue( $this->adapter()->file_exists( 'exports/token.zip' ) );

		$file = $this->adapter()->get_file( 'exports/token.zip' );
		self::assertSame( 'token.zip', $file->file_name );
		self::assertSame( 'application/zip', $file->mime_type );
		self::assertIsResource( $file->stream );
		fclose( $file->stream );

		$this->adapter()->delete_file( 'exports/token.zip' );

		self::assertFalse( $this->adapter()->file_exists( 'exports/token.zip' ) );
		unlink( $source );
	}

	public function test_persist_consumes_attachment_iterable_lazily_and_closes_streams(): void {
		$source = $this->root . '-source.csv';
		file_put_contents( $source, 'order_id,total' );

		$opened            = 0;
		$attachment_stream = null;
		$attachments       = static function () use ( &$opened, &$attachment_stream ): iterable {
			++$opened;
			$attachment_stream = fopen( 'php://temp', 'rb+' );

			self::assertIsResource( $attachment_stream );
			fwrite( $attachment_stream, 'invoice' );
			rewind( $attachment_stream );

			yield new ExportAttachment( $attachment_stream, 'invoice.pdf', 'application/pdf', 'Invoices/pdf/invoice.pdf' );
		};

		$configuration = new StorageFileConfiguration(
			'exports/token.zip',
			$source,
			'token.csv',
			'token.csv',
			$attachments(),
			'text/csv'
		);

		self::assertSame( 0, $opened );

		$result = $this->adapter()->persist( $configuration );

		self::assertSame( 'exports/token.zip', $result );
		self::assertSame( 1, $opened );
		self::assertNotNull( $attachment_stream );
		self::assertFalse( is_resource( $attachment_stream ) );

		$archive = new ZipArchive();
		self::assertTrue( $archive->open( $this->root . '/exports/token.zip' ) );
		self::assertSame( 'order_id,total', $archive->getFromName( 'token.csv' ) );
		self::assertSame( 'invoice', $archive->getFromName( 'Invoices/pdf/invoice.pdf' ) );
		$archive->close();

		unlink( $source );
	}

	public function test_chunked_persist_replaces_retried_attachment_paths(): void {
		$source = $this->root . '-source.csv';
		file_put_contents( $source, 'order_id,total' );

		$configuration = new StorageFileConfiguration(
			'exports/token.zip',
			$source,
			'token.csv',
			'token.csv',
			[],
			'text/csv'
		);

		self::assertSame( 'exports/token.zip', $this->adapter()->start_persist( $configuration ) );
		self::assertTrue(
			$this->adapter()->append_attachments(
				'exports/token.zip',
				[ $this->attachment( 'old invoice' ) ]
			)
		);
		self::assertTrue(
			$this->adapter()->append_attachments(
				'exports/token.zip',
				[ $this->attachment( 'new invoice' ) ]
			)
		);

		$archive = new ZipArchive();
		self::assertTrue( $archive->open( $this->root . '/exports/token.zip' ) );
		self::assertSame( 2, $archive->numFiles );
		self::assertSame( 'new invoice', $archive->getFromName( 'Invoices/pdf/invoice.pdf' ) );
		$archive->close();

		unlink( $source );
	}

	public function test_persist_returns_error_for_unreadable_source_file(): void {
		$result = $this->adapter()->persist(
			new StorageFileConfiguration( 'exports/missing.zip', $this->root . '/missing.csv', 'missing.csv' )
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_storage_source_file_not_readable', $result->get_error_code() );
	}

	public function test_delete_if_empty_removes_only_managed_empty_storage_directory(): void {
		self::assertTrue( $this->adapter()->ensure() );

		$this->adapter()->delete_if_empty();

		self::assertDirectoryDoesNotExist( $this->root );

		mkdir( $this->root, 0777, true );
		file_put_contents( $this->root . '/keep.txt', 'keep' );

		$this->adapter()->delete_if_empty();

		self::assertDirectoryExists( $this->root );
	}

	private function adapter(): LocalStorageAdapter {
		return new LocalStorageAdapter(
			new LocalStorageConfiguration( $this->root, 'wp-content/uploads/storeaccountant' )
		);
	}

	private function attachment( string $contents ): ExportAttachment {
		$stream = fopen( 'php://temp', 'rb+' );
		self::assertIsResource( $stream );
		fwrite( $stream, $contents );
		rewind( $stream );

		return new ExportAttachment( $stream, 'invoice.pdf', 'application/pdf', 'Invoices/pdf/invoice.pdf' );
	}

	private function delete_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		foreach ( scandir( $directory ) ?: [] as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? $this->delete_directory( $path ) : unlink( $path );
		}

		rmdir( $directory );
	}
}
