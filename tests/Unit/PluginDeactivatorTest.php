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

namespace StoreAccountant\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\PluginDeactivator;

/**
 * Tests plugin deactivation behavior.
 */
final class PluginDeactivatorTest extends TestCase {
	private string $upload_root;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->upload_root = sys_get_temp_dir() . '/storeaccountant-deactivation-' . uniqid( '', true );

		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/' ) . '/' );
		Functions\when( 'wp_upload_dir' )->alias(
			fn (): array => [
				'basedir' => $this->upload_root,
				'error'   => false,
			]
		);
		Functions\when( 'WP_Filesystem' )->alias( static fn (): bool => true );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);

		global $wp_filesystem;

		$wp_filesystem = new class() {
			public function rmdir( string $path ): bool {
				return rmdir( $path );
			}
		};
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->upload_root );

		global $wp_filesystem;

		$wp_filesystem = null;

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_deactivate_removes_only_empty_managed_local_storage_directory(): void {
		$storage_root = $this->upload_root . '/storeaccountant';

		mkdir( $storage_root, 0777, true );
		file_put_contents( $storage_root . '/index.html', '' );
		file_put_contents( $storage_root . '/.htaccess', 'deny from all' );

		PluginDeactivator::deactivate();

		self::assertDirectoryDoesNotExist( $storage_root );
	}

	public function test_deactivate_keeps_storage_directory_with_persisted_files(): void {
		$storage_root = $this->upload_root . '/storeaccountant';

		mkdir( $storage_root, 0777, true );
		file_put_contents( $storage_root . '/index.html', '' );
		file_put_contents( $storage_root . '/.htaccess', 'deny from all' );
		file_put_contents( $storage_root . '/export.zip', 'persisted export' );

		PluginDeactivator::deactivate();

		self::assertDirectoryExists( $storage_root );
		self::assertFileExists( $storage_root . '/export.zip' );
	}

	private function remove_directory( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( scandir( $path ) ?: [] as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$item_path = $path . '/' . $item;

			if ( is_dir( $item_path ) ) {
				$this->remove_directory( $item_path );
				continue;
			}

			unlink( $item_path );
		}

		rmdir( $path );
	}
}
