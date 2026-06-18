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

namespace StoreAccountant\Tests\Unit\Settings\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Invoice\Admin\InvoicePluginForm;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Queue\Admin\QueueTransportsSettingsForm;
use StoreAccountant\Queue\QueueTransportRegistry;
use StoreAccountant\Security\Admin\PermissionsSettingsForm;
use StoreAccountant\Security\Admin\SecuritySettingsForm;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\RolePermissionRepository;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Settings\Admin\PluginSettingsPage;
use StoreAccountant\Settings\Admin\PluginSettingsTabProviderRegistry;
use StoreAccountant\Storage\Admin\StorageLocationsForm;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests plugin settings page behavior.
 */
final class PluginSettingsPageTest extends TestCase {
	private bool $can_manage_settings = true;

	private bool $can_manage_permissions = true;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		if ( ! defined( 'STOREACCOUNTANT_FILE' ) ) {
			define( 'STOREACCOUNTANT_FILE', dirname( __DIR__, 3 ) . '/storeaccountant.php' );
		}

		$_GET  = [];
		$_POST = [];

		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text, string $domain = 'default' ): void {
				echo $text;
			}
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $text ): string => strip_tags( $text ) );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $key ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $key ) ?? '' )
		);
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'plugin_basename' )->returnArg( 1 );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . ltrim( $path, '/' ) );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array|string $key, mixed $value = null, string $url = '' ): string {
				if ( is_array( $key ) ) {
					$args = $key;
					$url  = (string) $value;
				} else {
					$args = [ $key => $value ];
				}

				return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
			}
		);
		Functions\when( 'filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_GET === $type ? ( $_GET[ $name ] ?? null ) : ( $_POST[ $name ] ?? null )
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_GET === $type ? ( $_GET[ $name ] ?? null ) : ( $_POST[ $name ] ?? null )
		);
		Functions\when( 'filter_input_array' )->alias(
			static fn ( int $type, mixed $definition = null ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input_array' )->alias(
			static fn ( int $type, mixed $definition = null ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( string $action, string $name ): void {
				echo '<input type="hidden" name="' . $name . '" value="nonce" />';
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'update_option' )->alias( static fn (): bool => true );
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_permission_action' => [
					new PermissionAction( PermissionActionIds::MANAGE_SETTINGS, 'Manage Settings', 'Settings', StoreAccountantCapabilities::MANAGE_SETTINGS ),
					new PermissionAction( PermissionActionIds::MANAGE_PERMISSIONS, 'Manage Permissions', 'Settings', StoreAccountantCapabilities::MANAGE_PERMISSIONS ),
				],
				default => $value,
			}
		);
		Functions\when( 'current_user_can' )->alias(
			fn ( string $capability ): bool => match ( $capability ) {
				'manage_options' => false,
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::MANAGE_SETTINGS => $this->can_manage_settings,
				StoreAccountantCapabilities::MANAGE_PERMISSIONS => $this->can_manage_permissions,
				default => false,
			}
		);
	}

	protected function tearDown(): void {
		$_GET  = [];
		$_POST = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_admin_hooks_and_plugin_action_link_filter(): void {
		$page = $this->page();

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', [ $page, 'register_page' ] );
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_post_storeaccountant_save_plugin_settings', [ $page, 'handle_save' ] );
		Functions\expect( 'add_filter' )->once();

		$page->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_filter_plugin_action_links_prepends_settings_link(): void {
		$links = $this->page()->filter_plugin_action_links( [ 'Deactivate' ] );

		self::assertCount( 2, $links );
		self::assertStringContainsString( 'Settings', $links[0] );
		self::assertStringContainsString( 'page=storeaccountant-settings', $links[0] );
		self::assertSame( 'Deactivate', $links[1] );
	}

	public function test_render_defaults_to_storage_tab_and_shows_saved_notice(): void {
		$_GET['tab']                            = 'unknown';
		$_GET['storeaccountant_settings_saved'] = '1';

		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'StoreAccountant Settings', $output );
		self::assertStringContainsString( 'StoreAccountant settings were saved.', $output );
		self::assertStringContainsString( 'value="storage-locations"', $output );
		self::assertStringContainsString( 'Storage adapter configuration', $output );
	}

	public function test_handle_save_checks_permissions_for_permissions_tab(): void {
		$this->can_manage_settings    = false;
		$this->can_manage_permissions = false;

		$_POST['storeaccountant_settings_tab'] = 'permissions';

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'storeaccountant_save_plugin_settings', 'storeaccountant_plugin_settings_nonce' );
		Functions\expect( 'wp_die' )->once()->andThrow( new RuntimeException( 'permission denied' ) );

		$this->expectException( RuntimeException::class );

		$this->page()->handle_save();
	}

	private function page(): PluginSettingsPage {
		$permission_actions = new PermissionActionRegistry();
		$role_permissions   = new RolePermissionRepository( $permission_actions );
		$permissions        = new PermissionChecker( $permission_actions );
		$storage_adapters   = new StorageAdapterRegistry();
		$invoice_plugins    = new InvoicePluginRegistry();
		$passwords          = new DownloadPasswordManager( new ReversibleCrypto() );

		return new PluginSettingsPage(
			$storage_adapters,
			new StorageLocationsForm( $storage_adapters ),
			$invoice_plugins,
			new InvoicePluginForm( $invoice_plugins ),
			new QueueTransportsSettingsForm( new QueueTransportRegistry() ),
			new PermissionsSettingsForm( $permission_actions, $role_permissions ),
			new SecuritySettingsForm( $passwords, $permissions ),
			new PluginSettingsTabProviderRegistry(),
			$role_permissions,
			$permissions,
			$passwords
		);
	}
}
