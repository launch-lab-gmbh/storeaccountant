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

namespace StoreAccountant\Tests\Unit\Diagnostic\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Diagnostic\Admin\DiagnosticSettingsTabProvider;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use WP_Error;

/**
 * Tests diagnostic settings tab behavior.
 */
final class DiagnosticSettingsTabProviderTest extends TestCase {
	private string $root;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->root = sys_get_temp_dir() . '/storeaccountant-diagnostic-settings-' . uniqid( '', true );

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
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_permission_action' => [
					new PermissionAction(
						PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE,
						'Manage diagnostic logging',
						'Administration',
						StoreAccountantCapabilities::MANAGE_DIAGNOSTICS
					),
				],
				default => $value,
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => match ( $capability ) {
				'manage_options' => false,
				StoreAccountantCapabilities::ACCESS_ADMIN,
				StoreAccountantCapabilities::MANAGE_DIAGNOSTICS => true,
				default => false,
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
		};
	}

	protected function tearDown(): void {
		$this->delete_directory( $this->root );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_save_enabled_prepares_log_directory_before_storing_option(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_diagnostic_logging_enabled', '1', false )
			->andReturn( true );

		$this->provider()->save(
			'diagnostics',
			[
				'storeaccountant_diagnostic_logging_enabled' => '1',
			]
		);

		self::assertDirectoryExists( $this->root );
		self::assertFileExists( $this->root . '/index.html' );
		self::assertFileExists( $this->root . '/.htaccess' );
	}

	public function test_save_disabled_stores_option_without_preparing_directory(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_diagnostic_logging_enabled', '0', false )
			->andReturn( true );

		$this->provider()->save( 'diagnostics', [] );

		self::assertDirectoryDoesNotExist( $this->root );
	}

	private function provider(): DiagnosticSettingsTabProvider {
		return new DiagnosticSettingsTabProvider(
			new DiagnosticSettings(),
			new PermissionChecker( new PermissionActionRegistry() ),
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
