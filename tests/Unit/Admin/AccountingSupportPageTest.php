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
use PHPUnit\Framework\TestCase;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingOverviewTabProviderRegistry;
use StoreAccountant\Admin\AccountingSupportAccess;
use StoreAccountant\Admin\AccountingSupportPage;
use StoreAccountant\Admin\ExportConfigurationOverviewTabProvider;
use StoreAccountant\Admin\ExportOverviewTabProvider;
use StoreAccountant\Admin\SupportOverviewTabProvider;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests the accounting support page.
 */
final class AccountingSupportPageTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->mock_wordpress_helpers();
		$this->mock_permission_actions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_outputs_support_copy_and_mailto_button(): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => StoreAccountantCapabilities::ACCESS_ADMIN === $capability
				|| StoreAccountantCapabilities::READ_EXPORTS === $capability
				|| StoreAccountantCapabilities::READ_CONFIGURATIONS === $capability
		);

		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'StoreAccountant Support', $output );
		self::assertStringContainsString( 'Found a bug or error?', $output );
		self::assertStringContainsString( 'Have you found bugs or errors in StoreAccountant?', $output );
		self::assertStringContainsString( 'Custom development', $output );
		self::assertStringContainsString( 'WooCommerce order exports, customer exports, CSV exports, and JSON exports', $output );
		self::assertStringContainsString( 'href="mailto:storeaccountant@launch-lab.de"', $output );
		self::assertStringContainsString( 'Contact StoreAccountant Support', $output );
	}

	public function test_register_page_adds_hidden_options_page_with_read_capability(): void {
		$page = $this->page();

		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'options.php',
				'Support',
				'Support',
				'read',
				AccountingSupportPage::PAGE_SLUG,
				[ $page, 'render' ]
		);

		$page->register_page();

		$this->addToAssertionCount( 1 );
	}

	public function test_render_allows_users_with_export_overview_access_only(): void {
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => StoreAccountantCapabilities::READ_EXPORTS === $capability
		);

		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'StoreAccountant Support', $output );
		self::assertStringContainsString( 'href="mailto:storeaccountant@launch-lab.de"', $output );
	}

	private function page(): AccountingSupportPage {
		$permissions = new PermissionChecker( new PermissionActionRegistry() );

		return new AccountingSupportPage(
			new AccountingHeaderBar(
				$permissions,
				new AccountingOverviewTabProviderRegistry()
			),
			new AccountingSupportAccess()
		);
	}

	private function mock_wordpress_helpers(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html__' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => htmlspecialchars( $url, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr__' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => 'https://example.test/wp-admin/' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array|string $key, ?string $value = null, ?string $url = null ): string {
				if ( is_array( $key ) ) {
					$args = $key;
					$url  = (string) $value;
				} else {
					$args = [ $key => (string) $value ];
					$url  = (string) $url;
				}

				return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
			}
		);
		Functions\when( 'wp_die' )->alias(
			static function ( string $message ): void {
				throw new \RuntimeException( $message );
			}
		);
	}

	private function mock_permission_actions(): void {
		$actions = [
			new PermissionAction( PermissionActionIds::EXPORT_LIST, 'List exports', 'Exports', StoreAccountantCapabilities::READ_EXPORTS ),
			new PermissionAction( PermissionActionIds::CONFIGURATION_LIST, 'List configurations', 'Configurations', StoreAccountantCapabilities::READ_CONFIGURATIONS ),
		];

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( $actions ): mixed {
				if ( 'storeaccountant_permission_action' === $hook ) {
					return $actions;
				}

				if ( 'storeaccountant_accounting_overview_tab_provider' === $hook ) {
					$permissions = new PermissionChecker( new PermissionActionRegistry() );

					return [
						ExportOverviewTabProvider::TAB_ID              => new ExportOverviewTabProvider( $permissions ),
						ExportConfigurationOverviewTabProvider::TAB_ID => new ExportConfigurationOverviewTabProvider( $permissions ),
						SupportOverviewTabProvider::TAB_ID             => new SupportOverviewTabProvider( new AccountingSupportAccess() ),
					];
				}

				return $value;
			}
		);
	}
}
