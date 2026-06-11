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

namespace StoreAccountant\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests StoreAccountant admin menu registration.
 */
final class AccountingMenuTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_admin_menu_hook(): void {
		$menu = $this->menu();

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', [ $menu, 'register_menu' ], 9 );

		$menu->register();

		self::assertTrue( true );
	}

	public function test_register_menu_adds_export_submenu_when_export_list_is_allowed(): void {
		$this->mock_translations();
		$this->mock_permission_actions();
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::READ_EXPORTS => true,
			]
		);

		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'Accounting',
				'Accounting',
				StoreAccountantCapabilities::ACCESS_ADMIN,
				AccountingMenu::MENU_SLUG,
				Mockery::type( 'array' ),
				'dashicons-media-spreadsheet',
				56
			);
		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				AccountingMenu::MENU_SLUG,
				'Exports',
				'Exports',
				StoreAccountantCapabilities::READ_EXPORTS,
				'edit.php?post_type=' . ExportPostType::POST_TYPE
			);
		Functions\expect( 'remove_submenu_page' )
			->once()
			->with( AccountingMenu::MENU_SLUG, AccountingMenu::MENU_SLUG );

		$this->menu()->register_menu();

		self::assertTrue( true );
	}

	public function test_register_menu_adds_configuration_submenu_when_exports_are_not_allowed(): void {
		$this->mock_translations();
		$this->mock_permission_actions();
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::READ_EXPORTS => false,
				StoreAccountantCapabilities::READ_CONFIGURATIONS => true,
			]
		);

		Functions\expect( 'add_menu_page' )
			->once()
			->with(
				'Accounting',
				'Accounting',
				StoreAccountantCapabilities::ACCESS_ADMIN,
				AccountingMenu::MENU_SLUG,
				Mockery::type( 'array' ),
				'dashicons-media-spreadsheet',
				56
			);
		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				AccountingMenu::MENU_SLUG,
				'Export Configurations',
				'Export Configurations',
				StoreAccountantCapabilities::READ_CONFIGURATIONS,
				'edit.php?post_type=' . ExportConfigurationPostType::POST_TYPE
			);
		Functions\expect( 'remove_submenu_page' )
			->once()
			->with( AccountingMenu::MENU_SLUG, AccountingMenu::MENU_SLUG );

		$this->menu()->register_menu();

		self::assertTrue( true );
	}

	public function test_register_menu_does_not_add_pages_without_admin_access(): void {
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => false,
			]
		);

		Functions\expect( 'add_menu_page' )->never();
		Functions\expect( 'add_submenu_page' )->never();
		Functions\expect( 'remove_submenu_page' )->never();

		$this->menu()->register_menu();

		self::assertTrue( true );
	}

	public function test_render_outputs_fallback_page_without_export_or_configuration_access(): void {
		$this->mock_permission_actions();
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => false,
				StoreAccountantCapabilities::READ_EXPORTS => false,
				StoreAccountantCapabilities::READ_CONFIGURATIONS => false,
			]
		);
		Functions\expect( 'wp_safe_redirect' )->never();
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);

		ob_start();
		$this->menu()->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'Accounting', $output );
		self::assertStringContainsString( 'You do not have access to any StoreAccountant overview.', $output );
	}

	private function menu(): AccountingMenu {
		return new AccountingMenu( new PermissionChecker( new PermissionActionRegistry() ) );
	}

	private function mock_translations(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
	}

	private function mock_permission_actions(): void {
		$actions = [
			new PermissionAction( PermissionActionIds::EXPORT_LIST, 'List exports', 'Exports', StoreAccountantCapabilities::READ_EXPORTS ),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_LIST,
				'List configurations',
				'Configurations',
				StoreAccountantCapabilities::READ_CONFIGURATIONS
			),
		];

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( $actions ): mixed {
				return 'storeaccountant_permission_action' === $hook ? $actions : $value;
			}
		);
	}

	/**
	 * @param array<string, bool> $capability_results Current user capability results.
	 */
	private function mock_current_user_can( array $capability_results ): void {
		Functions\when( 'current_user_can' )->alias(
			static function ( string $capability ) use ( $capability_results ): bool {
				if ( 'manage_options' === $capability ) {
					return false;
				}

				return $capability_results[ $capability ] ?? false;
			}
		);
	}
}
