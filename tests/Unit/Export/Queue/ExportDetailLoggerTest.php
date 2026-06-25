<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Tests\Unit\Export\Queue;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Queue\ExportDetailLogger;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use WP_Error;

/**
 * Tests per-export diagnostic detail logging.
 */
final class ExportDetailLoggerTest extends TestCase {
	private string $root;

	/** @var array<string, mixed> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->root = sys_get_temp_dir() . '/storeaccountant-export-detail-' . uniqid( '', true );
		$this->meta = [];

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-24 10:00:00' );
		Functions\when( 'get_post_meta' )->alias( fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? '' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value, int $flags = 0 ): string|false => json_encode( $value, $flags ) );
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

			public function get_contents( string $path ): string|false {
				return is_file( $path ) ? file_get_contents( $path ) : false;
			}
		};
	}

	protected function tearDown(): void {
		$this->delete_directory( $this->root );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_log_writes_export_detail_file_without_wordpress_debug_log(): void {
		$this->meta[ ExportPostType::META_DOWNLOAD_TOKEN ] = 'abc123token';

		Functions\when( 'get_option' )->alias( static fn (): string => '1' );
		Functions\expect( 'wp_trigger_error' )->never();

		$this->logger()->log(
			42,
			'warning',
			'export_invoice_attachment_skipped',
			[
				'batch'  => 6,
				'offset' => 500,
				'object' => new \stdClass(),
			],
			new RuntimeException( 'Invoice plugin failed.' )
		);

		$path = $this->root . '/export-abc123token.log';

		self::assertFileExists( $path );
		$lines = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		self::assertIsArray( $lines );
		self::assertCount( 1, $lines );

		$payload = json_decode( $lines[0], true );

		self::assertIsArray( $payload );
		self::assertSame( '2026-06-24 10:00:00', $payload['time'] );
		self::assertSame( 'warning', $payload['level'] );
		self::assertSame( 'export_invoice_attachment_skipped', $payload['event'] );
		self::assertSame( 42, $payload['export']['id'] );
		self::assertSame( 'abc123token', $payload['export']['download_token'] );
		self::assertSame( 6, $payload['context']['batch'] );
		self::assertSame( 500, $payload['context']['offset'] );
		self::assertSame( 'unserializable', $payload['context']['object'] );
		self::assertSame( RuntimeException::class, $payload['exception']['class'] );
		self::assertSame( 'Invoice plugin failed.', $payload['exception']['message'] );
		self::assertArrayHasKey( 'usage_bytes', $payload['memory'] );
		self::assertArrayHasKey( 'peak_bytes', $payload['memory'] );
		self::assertArrayHasKey( 'limit', $payload['memory'] );
	}

	public function test_log_does_not_write_when_diagnostics_are_disabled(): void {
		$this->meta[ ExportPostType::META_DOWNLOAD_TOKEN ] = 'abc123token';

		Functions\when( 'get_option' )->alias( static fn (): string => '0' );
		Functions\expect( 'wp_trigger_error' )->never();

		$this->logger()->log( 42, 'info', 'export_batch_started' );

		self::assertFileDoesNotExist( $this->root . '/export-abc123token.log' );
	}

	private function logger(): ExportDetailLogger {
		$configuration = new DiagnosticLogConfiguration( $this->root, 'wp-content/uploads/storeaccountant/logging' );

		return new ExportDetailLogger(
			new DiagnosticSettings(),
			new DiagnosticIncidentRepository( $configuration, new ProtectedUploadDirectory() ),
			$configuration
		);
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
