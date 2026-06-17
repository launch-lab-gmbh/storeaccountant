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

namespace StoreAccountant\Tests\Unit\Export\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Export\Admin\AccountingExportPage;
use StoreAccountant\Export\Admin\AccountingExportPageForm;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
use StoreAccountant\Queue\QueueTransportRegistry;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use StoreAccountant\Storage\StorageAdapterRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tests accounting export admin page guards and hook registration.
 */
final class AccountingExportPageTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		if ( ! defined( 'StoreAccountant\\Queue\\Loopback\\MINUTE_IN_SECONDS' ) ) {
			define( 'StoreAccountant\\Queue\\Loopback\\MINUTE_IN_SECONDS', 60 );
		}
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
		Functions\expect( 'add_action' )->once()->with( 'admin_post_storeaccountant_start_export', [ $page, 'handle_start_export' ] );
		Functions\expect( 'add_action' )->once()->with( 'admin_post_storeaccountant_start_export_from_overview', [ $page, 'handle_start_export_from_overview' ] );
		Functions\expect( 'add_filter' )->once()->with( 'parent_file', [ $page, 'filter_parent_file' ] );
		Functions\expect( 'add_filter' )->once()->with( 'submenu_file', [ $page, 'filter_submenu_file' ] );

		$page->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_add_submenu_page_uses_export_create_capability(): void {
		$page = $this->page();

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				AccountingMenu::MENU_SLUG,
				'Create New Export',
				'Create New Export',
				StoreAccountantCapabilities::CREATE_EXPORTS,
				AccountingExportPage::PAGE_SLUG,
				[ $page, 'render' ]
			);

		$page->add_submenu_page();

		$this->addToAssertionCount( 1 );
	}

	public function test_handle_start_export_redirects_invalid_quick_export_batch_size(): void {
		$_POST = [
			'storeaccountant_quick_export'      => '1',
			'storeaccountant_export_title'      => 'May Export',
			'storeaccountant_storage_engine'    => 'local',
			'storeaccountant_export_adapter'    => 'orders',
			'storeaccountant_export_writer'     => 'csv',
			'storeaccountant_export_batch_size' => '5',
		];

		Functions\expect( 'check_admin_referer' )
			->twice()
			->with( 'storeaccountant_start_export', 'storeaccountant_export_nonce' );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/admin.php?page=storeaccountant-export-create&storeaccountant_export_error=invalid_batch_size' )
			->andThrow( new RuntimeException( 'redirect_invalid_batch_size' ) );

		$this->expectExceptionMessage( 'redirect_invalid_batch_size' );

		$this->page()->handle_start_export();
	}

	public function test_handle_start_export_from_overview_requires_title_for_configuration_export(): void {
		$_POST = [
			'storeaccountant_export_create_selection' => 'configuration:42',
			'storeaccountant_export_title'            => '',
		];

		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'storeaccountant_start_export_from_overview', 'storeaccountant_export_overview_nonce' );
		Functions\expect( 'wp_safe_redirect' )
			->once()
			->with( 'https://example.test/wp-admin/edit.php?post_type=storeacct_export&storeaccountant_export_error=missing_title' )
			->andThrow( new RuntimeException( 'redirect_missing_title' ) );

		$this->expectExceptionMessage( 'redirect_missing_title' );

		$this->page()->handle_start_export_from_overview();
	}

	private function page(): AccountingExportPage {
		$permissions = new PermissionChecker( new PermissionActionRegistry() );
		$passwords   = new DownloadPasswordManager( new ReversibleCrypto() );

		return new AccountingExportPage(
			new AccountingExportPageForm(
				new StorageAdapterRegistry(),
				new ExportAdapterRegistry(),
				new ExportRendererRegistry(),
				new ExportFilterFieldProviderRegistry(),
				$passwords,
				$permissions
			),
			new ExportRepository( new ExportFilterSelectionSerializer(), $passwords ),
			$this->createMock( MessageBusInterface::class ),
			new StorageAdapterRegistry(),
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			new ExportFilterFieldProviderRegistry(),
			new ExportFilterSelectionSerializer(),
			new ExportFilterSnapshotter( new PeriodProviderRegistry() ),
			new AccountingHeaderBar( $permissions ),
			$permissions,
			new QueueLoopbackDispatcher( new QueueTransportRegistry() ),
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
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'filter_input_array' )->alias(
			static fn ( int $type ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input_array' )->alias(
			static fn ( int $type ): array => INPUT_POST === $type ? $_POST : $_GET
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . ltrim( $path, '/' ) );
		Functions\when( 'add_query_arg' )->alias( static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args ) );
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array(
				$capability,
				[ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::CREATE_EXPORTS ],
				true
			)
		);
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_permission_action' === $hook
				? [
					new PermissionAction( PermissionActionIds::EXPORT_CREATE, 'Create exports', 'Exports', StoreAccountantCapabilities::CREATE_EXPORTS ),
				]
				: $value
		);
	}
}
