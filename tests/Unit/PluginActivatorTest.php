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
use StoreAccountant\PluginActivator;
use StoreAccountant\Queue\Loopback\QueueLoopbackEndpoint;

/**
 * Tests plugin activation behavior.
 */
final class PluginActivatorTest extends TestCase {
	private string $upload_root;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->upload_root = sys_get_temp_dir() . '/storeaccountant-activation-' . uniqid( '', true );

		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/' ) . '/' );
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => mkdir( $path, 0777, true ) || is_dir( $path ) );
		Functions\when( 'wp_is_writable' )->alias( static fn ( string $path ): bool => is_writable( $path ) );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);
		Functions\when( 'wp_upload_dir' )->alias(
			fn (): array => [
				'basedir' => $this->upload_root,
				'error'   => false,
			]
		);
		Functions\when( 'WP_Filesystem' )->alias( static fn (): bool => true );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_salt' )->alias( static fn ( string $scheme = 'auth' ): string => 'unit-test-salt-' . $scheme );
		Functions\when( 'wp_generate_password' )->alias( static fn (): string => 'generated-password' );
		Functions\when( 'wp_hash_password' )->alias( static fn ( string $password ): string => 'hash-' . $password );
		Functions\when( 'update_option' )->alias( static fn (): bool => true );

		global $wp_filesystem;

		$wp_filesystem = new class() {
			public function put_contents( string $path, string $contents ): bool {
				return false !== file_put_contents( $path, $contents );
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

	public function test_activate_prepares_local_storage_password_and_rewrite_rules_once(): void {
		Functions\expect( 'add_rewrite_rule' )
			->once()
			->with(
				'^storeaccountant/export-download/([^/]+)/?$',
				'index.php?storeaccountant_export_download=$matches[1]',
				'top'
			);
		Functions\expect( 'add_rewrite_rule' )
			->once()
			->with(
				'^' . QueueLoopbackEndpoint::ROUTE_PATH . '/?$',
				'index.php?storeaccountant_queue_loopback=1',
				'top'
			);
		Functions\expect( 'flush_rewrite_rules' )->once();
		Functions\expect( 'set_transient' )->never();

		PluginActivator::activate();

		$storage_root = $this->upload_root . '/storeaccountant';

		self::assertDirectoryExists( $storage_root );
		self::assertFileExists( $storage_root . '/index.html' );
		self::assertFileExists( $storage_root . '/.htaccess' );
		self::assertDirectoryExists( $storage_root . '/logging' );
		self::assertFileExists( $storage_root . '/logging/index.html' );
		self::assertFileExists( $storage_root . '/logging/.htaccess' );
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
