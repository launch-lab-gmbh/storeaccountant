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

namespace StoreAccountant\Tests\Unit\Diagnostic;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use WP_Error;

/**
 * Tests diagnostic incident logging.
 */
final class DiagnosticIncidentLoggerTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->root = sys_get_temp_dir() . '/storeaccountant-diagnostics-' . uniqid( '', true );

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-17 10:00:00' );
		Functions\when( 'get_option' )->alias( static fn (): string => '1' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_generate_uuid4' )->alias( static fn (): string => '123e4567-e89b-12d3-a456-426614174000' );
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

	public function test_error_writes_incident_file_and_wordpress_debug_log(): void {
		Functions\expect( 'wp_trigger_error' )
			->once()
			->with(
				'StoreAccountant\\Diagnostic\\DiagnosticIncidentLogger::log_to_wordpress_debug',
				Mockery::type( 'string' ),
				E_USER_WARNING
			);

		$incident = $this->logger()->error(
			'export_configuration',
			'The export configuration could not be saved.',
			[ 'reason' => 'invalid_filters' ]
		);

		self::assertNotNull( $incident );
		self::assertSame( '123e4567-e89b-12d3-a456-426614174000', $incident->support_id );
		self::assertFileExists( $this->root . '/123e4567-e89b-12d3-a456-426614174000.json' );
	}

	private function logger(): DiagnosticIncidentLogger {
		return new DiagnosticIncidentLogger(
			new DiagnosticSettings(),
			new DiagnosticIncidentRepository(
				new DiagnosticLogConfiguration( $this->root, 'wp-content/uploads/storeaccountant/logging' ),
				new ProtectedUploadDirectory()
			)
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
