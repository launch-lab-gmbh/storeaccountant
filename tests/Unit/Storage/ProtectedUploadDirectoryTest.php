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

namespace StoreAccountant\Tests\Unit\Storage;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use WP_Error;

/**
 * Tests protected upload directory preparation.
 */
final class ProtectedUploadDirectoryTest extends TestCase {
	private string $root = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->root = sys_get_temp_dir() . '/storeaccountant-protected-directory-' . bin2hex( random_bytes( 4 ) );

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
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
		unset( $GLOBALS['wp_filesystem'] );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_ensure_protects_managed_directory_hierarchy(): void {
		$directory = $this->root . '/storeaccountant/tmp/exports/42';

		self::assertTrue(
			( new ProtectedUploadDirectory() )->ensure(
				$directory,
				'wp-content/uploads/storeaccountant/tmp/exports/42'
			)
		);

		foreach ( [ 'storeaccountant', 'storeaccountant/tmp', 'storeaccountant/tmp/exports', 'storeaccountant/tmp/exports/42' ] as $path ) {
			self::assertFileExists( $this->root . '/' . $path . '/index.html' );
			self::assertFileExists( $this->root . '/' . $path . '/.htaccess' );
			self::assertSame( 'deny from all', file_get_contents( $this->root . '/' . $path . '/.htaccess' ) );
		}
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
