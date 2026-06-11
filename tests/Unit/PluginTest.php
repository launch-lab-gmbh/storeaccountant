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
use StoreAccountant\Plugin;

/**
 * Tests plugin bootstrapping.
 */
final class PluginTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		if ( ! defined( 'STOREACCOUNTANT_FILE' ) ) {
			define( 'STOREACCOUNTANT_FILE', dirname( __DIR__, 2 ) . '/storeaccountant.php' );
		}

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => $key );
		Functions\when( 'wp_upload_dir' )->alias(
			static fn (): array => [
				'basedir' => '/tmp/storeaccountant-uploads',
				'error'   => false,
			]
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/' ) . '/' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'plugin_basename' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_boot_builds_container_and_registers_hook_services(): void {
		Functions\expect( 'add_action' )->atLeast()->once();
		Functions\expect( 'add_filter' )->atLeast()->once();

		( new Plugin() )->boot();

		$this->addToAssertionCount( 1 );
	}
}
