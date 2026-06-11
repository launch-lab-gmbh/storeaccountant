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

namespace StoreAccountant\Tests\Unit\Export\Download;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Download\StorageFileStreamer;
use StoreAccountant\Storage\StorageFile;

/**
 * Tests browser streaming headers for stored export files.
 */
final class StorageFileStreamerTest extends TestCase {
	/** @var array<int, string> */
	private array $headers = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'sanitize_file_name' )->alias(
			static fn ( string $file_name ): string => trim( str_replace( [ '"', '/', '\\' ], '', $file_name ) )
		);
		Functions\when( 'ob_get_level' )->justReturn( 0 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_stream_sets_download_headers_and_closes_stream(): void {
		$stream = fopen( 'php://temp', 'rb+' );
		self::assertIsResource( $stream );
		fwrite( $stream, 'data' );
		rewind( $stream );

		Functions\expect( 'nocache_headers' )->once();
		Functions\expect( 'header' )
			->times( 5 )
			->andReturnUsing(
				function ( string $header ): void {
					$this->headers[] = $header;
				}
			);
		Functions\expect( 'fpassthru' )
			->once()
			->with( $stream )
			->andReturn( 4 );
		Functions\expect( 'fclose' )
			->once()
			->with( $stream )
			->andThrow( new RuntimeException( 'stream_closed' ) );

		$this->expectExceptionMessage( 'stream_closed' );

		try {
			( new StorageFileStreamer() )->stream( new StorageFile( $stream, 'orders "may".csv', 'text/csv' ) );
		} finally {
			self::assertContains( 'Content-Type: text/csv', $this->headers );
			self::assertContains( 'Content-Length: 4', $this->headers );
			self::assertContains( 'Content-Transfer-Encoding: binary', $this->headers );
			self::assertContains( 'X-Content-Type-Options: nosniff', $this->headers );
			self::assertTrue(
				in_array( 'Content-Disposition: attachment; filename="orders may.csv"; filename*=UTF-8\'\'orders%20may.csv', $this->headers, true )
			);
		}
	}

	public function test_stream_rejects_missing_stream_with_controlled_error(): void {
		Functions\expect( 'wp_die' )
			->once()
			->with(
				'The requested export file is unavailable.',
				'Export Unavailable',
				[ 'response' => 404 ]
			)
			->andThrow( new RuntimeException( 'wp_die' ) );

		$this->expectExceptionMessage( 'wp_die' );

		( new StorageFileStreamer() )->stream( new StorageFile( null, 'orders.csv', 'text/csv' ) );
	}

	public function test_stream_falls_back_to_safe_file_name_and_mime_type(): void {
		$stream = fopen( 'php://temp', 'rb+' );
		self::assertIsResource( $stream );

		Functions\expect( 'nocache_headers' )->once();
		Functions\expect( 'header' )
			->times( 5 )
			->andReturnUsing(
				function ( string $header ): void {
					$this->headers[] = $header;
				}
			);
		Functions\expect( 'fpassthru' )->once()->andReturn( 0 );
		Functions\expect( 'fclose' )->once()->andThrow( new RuntimeException( 'stream_closed' ) );

		$this->expectExceptionMessage( 'stream_closed' );

		try {
			( new StorageFileStreamer() )->stream( new StorageFile( $stream, '"/"', 'bad mime' ) );
		} finally {
			self::assertContains( 'Content-Type: application/octet-stream', $this->headers );
			self::assertTrue(
				in_array( 'Content-Disposition: attachment; filename="storeaccountant-export"; filename*=UTF-8\'\'storeaccountant-export', $this->headers, true )
			);
		}
	}
}
