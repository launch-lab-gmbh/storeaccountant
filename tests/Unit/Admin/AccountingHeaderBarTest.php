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
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests the accounting admin header bar rendering decisions.
 */
final class AccountingHeaderBarTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->mock_wordpress_output_helpers();
		$this->mock_permission_actions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_overview_actions_outputs_export_tab_create_form_and_configuration_options(): void {
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::READ_EXPORTS => true,
				StoreAccountantCapabilities::READ_CONFIGURATIONS => true,
				StoreAccountantCapabilities::CREATE_EXPORTS => true,
				StoreAccountantCapabilities::MANAGE_SETTINGS => true,
				StoreAccountantCapabilities::MANAGE_PERMISSIONS => false,
				StoreAccountantCapabilities::CREATE_CONFIGURATIONS => false,
			]
		);
		Functions\when( 'get_posts' )->alias(
			static fn ( array $args ): array => [
				(object) [
					'ID'         => 23,
					'post_title' => 'Monthly & Special',
				],
			]
		);
		Functions\when( 'get_the_title' )->alias(
			static fn ( object $post ): string => (string) $post->post_title
		);

		$output = $this->render( static fn ( AccountingHeaderBar $bar ): null => $bar->render_overview_actions() );

		self::assertStringContainsString( 'href="https://example.test/wp-admin/admin.php?page=storeaccountant-settings"', $output );
		self::assertStringContainsString( 'href="https://example.test/wp-admin/edit.php?post_type=' . ExportPostType::POST_TYPE . '"', $output );
		self::assertStringContainsString( 'nav-tab nav-tab-active', $output );
		self::assertStringContainsString( 'href="https://example.test/wp-admin/edit.php?post_type=' . ExportConfigurationPostType::POST_TYPE . '"', $output );
		self::assertStringContainsString( 'action="https://example.test/wp-admin/admin-post.php"', $output );
		self::assertStringContainsString( 'name="action" value="storeaccountant_start_export_from_overview"', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_overview_nonce" value="nonce-storeaccountant_start_export_from_overview"', $output );
		self::assertStringContainsString( 'value="configuration:23"', $output );
		self::assertStringContainsString( 'Monthly &amp; Special', $output );
	}

	public function test_render_configuration_actions_marks_configuration_tab_and_links_create_page(): void {
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::READ_EXPORTS => true,
				StoreAccountantCapabilities::READ_CONFIGURATIONS => true,
				StoreAccountantCapabilities::CREATE_CONFIGURATIONS => true,
				StoreAccountantCapabilities::MANAGE_SETTINGS => false,
				StoreAccountantCapabilities::MANAGE_PERMISSIONS => true,
			]
		);

		$output = $this->render( static fn ( AccountingHeaderBar $bar ): null => $bar->render_configuration_actions() );

		self::assertStringContainsString( 'Configure Accounting Plugin', $output );
		self::assertStringContainsString( 'href="https://example.test/wp-admin/admin.php?page=storeaccountant-settings"', $output );
		self::assertStringContainsString( 'href="https://example.test/wp-admin/admin.php?page=storeaccountant-export-configuration"', $output );
		self::assertStringContainsString( 'Create New Export Configuration', $output );
		self::assertStringContainsString( 'Export Configurations', $output );
		self::assertStringContainsString( 'nav-tab nav-tab-active', $output );
	}

	public function test_render_detail_actions_links_back_to_export_overview(): void {
		$this->mock_current_user_can( [] );

		$output = $this->render( static fn ( AccountingHeaderBar $bar ): null => $bar->render_detail_actions() );

		self::assertStringContainsString( 'href="https://example.test/wp-admin/edit.php?post_type=' . ExportPostType::POST_TYPE . '"', $output );
		self::assertStringContainsString( 'Back to Accounting Overview', $output );
	}

	public function test_render_configuration_detail_actions_uses_custom_return_url_and_edit_permission(): void {
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::EDIT_CONFIGURATION => true,
			]
		);

		$output = $this->render(
			static fn ( AccountingHeaderBar $bar ): null => $bar->render_configuration_detail_actions(
				'https://example.test/custom-return?x=1&y=2',
				'https://example.test/edit-configuration?post=99&action=edit',
				99
			)
		);

		self::assertStringContainsString( 'href="https://example.test/custom-return?x=1&amp;y=2"', $output );
		self::assertStringContainsString( 'href="https://example.test/edit-configuration?post=99&amp;action=edit"', $output );
		self::assertStringContainsString( 'Edit Export Configuration', $output );
	}

	public function test_render_configuration_detail_actions_hides_edit_link_without_permission(): void {
		$this->mock_current_user_can(
			[
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::EDIT_CONFIGURATION => false,
			]
		);

		$output = $this->render(
			static fn ( AccountingHeaderBar $bar ): null => $bar->render_configuration_detail_actions(
				null,
				'https://example.test/edit-configuration?post=99&action=edit',
				99
			)
		);

		self::assertStringContainsString( 'href="https://example.test/wp-admin/edit.php?post_type=' . ExportConfigurationPostType::POST_TYPE . '"', $output );
		self::assertStringNotContainsString( 'Edit Export Configuration', $output );
	}

	/**
	 * @param callable(AccountingHeaderBar): void $callback Render callback.
	 */
	private function render( callable $callback ): string {
		ob_start();
		$callback( new AccountingHeaderBar( new PermissionChecker( new PermissionActionRegistry() ) ) );

		return (string) ob_get_clean();
	}

	private function mock_wordpress_output_helpers(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_attr__' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_url' )->alias( static fn ( string $url ): string => htmlspecialchars( $url, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'admin_url' )->alias( static fn ( string $path ): string => 'https://example.test/wp-admin/' . $path );
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( string $action, string $name ): void {
				echo '<input type="hidden" name="' . htmlspecialchars( $name, ENT_QUOTES ) . '" value="nonce-' . htmlspecialchars( $action, ENT_QUOTES ) . '" />';
			}
		);
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
	}

	private function mock_permission_actions(): void {
		$actions = [
			new PermissionAction( PermissionActionIds::EXPORT_LIST, 'List exports', 'Exports', StoreAccountantCapabilities::READ_EXPORTS ),
			new PermissionAction( PermissionActionIds::EXPORT_CREATE, 'Create exports', 'Exports', StoreAccountantCapabilities::CREATE_EXPORTS ),
			new PermissionAction( PermissionActionIds::CONFIGURATION_LIST, 'List configurations', 'Configurations', StoreAccountantCapabilities::READ_CONFIGURATIONS ),
			new PermissionAction( PermissionActionIds::CONFIGURATION_CREATE, 'Create configurations', 'Configurations', StoreAccountantCapabilities::CREATE_CONFIGURATIONS ),
			new PermissionAction( PermissionActionIds::CONFIGURATION_EDIT, 'Edit configurations', 'Configurations', StoreAccountantCapabilities::EDIT_CONFIGURATION ),
			new PermissionAction( PermissionActionIds::MANAGE_SETTINGS, 'Manage settings', 'Settings', StoreAccountantCapabilities::MANAGE_SETTINGS ),
			new PermissionAction( PermissionActionIds::MANAGE_PERMISSIONS, 'Manage permissions', 'Settings', StoreAccountantCapabilities::MANAGE_PERMISSIONS ),
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
