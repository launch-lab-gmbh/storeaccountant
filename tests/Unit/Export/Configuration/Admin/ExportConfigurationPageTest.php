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

namespace StoreAccountant\Tests\Unit\Export\Configuration\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Admin\AccountingOverviewTabProviderRegistry;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Export\Admin\ExportSettingsFields;
use StoreAccountant\Export\Configuration\Admin\ExportConfigurationPage;
use StoreAccountant\Export\Configuration\Admin\ExportConfigurationPageForm;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Configuration\ExportConfigurationRepository;
use StoreAccountant\Export\Configuration\ExportConfigurationTabProviderRegistry;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;

/**
 * Tests export configuration admin page guards and save validation.
 */
final class ExportConfigurationPageTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$_POST = [];
		$this->mock_wordpress_functions();
	}

	protected function tearDown(): void {
		$_POST = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_admin_hooks_and_filters(): void {
		$page = $this->page();

		Functions\expect( 'add_action' )->once()->with( 'admin_menu', [ $page, 'add_submenu_page' ] );
		Functions\expect( 'add_action' )->once()->with( 'admin_head', [ $page, 'remove_hidden_submenu_page' ] );
		Functions\expect( 'add_action' )->once()->with( 'admin_post_storeaccountant_save_export_configuration', [ $page, 'handle_save' ] );
		Functions\expect( 'add_filter' )->once()->with( 'admin_title', [ $page, 'filter_admin_title' ], 10, 2 );
		Functions\expect( 'add_filter' )->once()->with( 'parent_file', [ $page, 'filter_parent_file' ] );
		Functions\expect( 'add_filter' )->once()->with( 'submenu_file', [ $page, 'filter_submenu_file' ] );

		$page->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_add_submenu_page_registers_hidden_configuration_page(): void {
		$page = $this->page();

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				AccountingMenu::MENU_SLUG,
				'Create Export Configuration',
				'Create Export Configuration',
				StoreAccountantCapabilities::ACCESS_ADMIN,
				'storeaccountant-export-configuration',
				[ $page, 'render' ]
			);

		$page->add_submenu_page();

		$this->addToAssertionCount( 1 );
	}

	public function test_handle_save_redirects_when_title_is_missing(): void {
		$_POST = [
			'storeaccountant_export_configuration_title' => '',
			'storeaccountant_export_adapter'             => 'orders',
		];

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'storeaccountant_save_export_configuration', 'storeaccountant_export_configuration_nonce' );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/admin.php?page=storeaccountant-export-configuration&storeaccountant_export_configuration_error=1' )
			->andThrow( new RuntimeException( 'redirect_missing_title' ) );

		$this->expectExceptionMessage( 'redirect_missing_title' );

		$this->page()->handle_save();
	}

	public function test_handle_save_redirects_invalid_batch_size(): void {
		$_POST = [
			'storeaccountant_export_configuration_title' => 'Monthly Orders',
			'storeaccountant_export_adapter'             => 'orders',
			'storeaccountant_export_batch_size'          => 'not-numeric',
			'storeaccountant_configuration_download_password' => 'secret',
		];

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'storeaccountant_save_export_configuration', 'storeaccountant_export_configuration_nonce' );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/admin.php?page=storeaccountant-export-configuration&storeaccountant_export_configuration_error=invalid_batch_size' )
			->andThrow( new RuntimeException( 'redirect_invalid_batch_size' ) );

		$this->expectExceptionMessage( 'redirect_invalid_batch_size' );

		$this->page()->handle_save();
	}

	private function page(): ExportConfigurationPage {
		$permissions     = new PermissionChecker( new PermissionActionRegistry() );
		$passwords       = new DownloadPasswordManager( new ReversibleCrypto() );
		$filter_registry = new ExportFilterFieldProviderRegistry();
		$settings_fields = $this->settings_fields();

		return new ExportConfigurationPage(
			new ExportConfigurationPageForm(
				new ExportAdapterRegistry(),
				$filter_registry,
				new ExportFilterSelectionSerializer(),
				$settings_fields,
				$passwords,
				$permissions
			),
			new ExportConfigurationRepository( new ExportFilterSelectionSerializer() ),
			new StorageAdapterRegistry(),
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			$filter_registry,
			new AccountingHeaderBar( $permissions, new AccountingOverviewTabProviderRegistry() ),
			new ExportConfigurationTabProviderRegistry(),
			$settings_fields,
			$permissions,
			$passwords,
			new DiagnosticIncidentLogger(
				new DiagnosticSettings(),
				new DiagnosticIncidentRepository(
					new DiagnosticLogConfiguration( '', 'wp-content/uploads/storeaccountant/logging' ),
					new ProtectedUploadDirectory()
				)
			)
		);
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'filter_input_array' )->alias(
			static fn ( int $type, mixed $definition = null ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input_array' )->alias(
			static fn ( int $type, mixed $definition = null ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . ltrim( $path, '/' ) );
		Functions\when( 'add_query_arg' )->alias( static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args ) );
		Functions\when( 'wp_salt' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_trigger_error' )->justReturn( null );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array(
				$capability,
				[
					StoreAccountantCapabilities::ACCESS_ADMIN,
					StoreAccountantCapabilities::CREATE_CONFIGURATIONS,
					StoreAccountantCapabilities::EDIT_CONFIGURATION,
				],
				true
			)
		);
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_storage_adapter' => [ $this->storage_adapter() ],
				'storeaccountant_export_adapter' => [ $this->export_adapter() ],
				'storeaccountant_export_renderer' => [ $this->renderer() ],
				'storeaccountant_permission_action' => [
					new PermissionAction( PermissionActionIds::CONFIGURATION_CREATE, 'Create configurations', 'Configurations', StoreAccountantCapabilities::CREATE_CONFIGURATIONS ),
					new PermissionAction( PermissionActionIds::CONFIGURATION_EDIT, 'Edit configurations', 'Configurations', StoreAccountantCapabilities::EDIT_CONFIGURATION ),
				],
				default => $value,
			}
		);
	}

	private function storage_adapter(): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'local' );

		return $adapter;
	}

	private function export_adapter(): ExportAdapterInterface {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );

		return $adapter;
	}

	private function renderer(): ExportRendererInterface {
		$renderer = $this->createMock( ExportRendererInterface::class );
		$renderer->method( 'get_id' )->willReturn( 'csv' );

		return $renderer;
	}

	private function settings_fields(): ExportSettingsFields {
		return new ExportSettingsFields(
			new StorageAdapterRegistry(),
			new ExportRendererRegistry(),
			new ExportConfigurationFormFieldProviderRegistry(),
			new OrderTaxFieldProviderField( new OrderTaxFieldProviderRegistry() )
		);
	}
}
