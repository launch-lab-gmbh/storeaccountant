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

namespace {
	if ( ! class_exists( 'WP_Role' ) ) {
		/**
		 * Minimal WordPress role double for permission form unit tests.
		 */
		class WP_Role {
			/**
			 * @param array<string, bool> $capabilities Role capabilities.
			 */
			public function __construct(
				public array $capabilities = []
			) {}

			public function has_cap( string $capability ): bool {
				return ! empty( $this->capabilities[ $capability ] );
			}

			public function add_cap( string $capability ): void {
				$this->capabilities[ $capability ] = true;
			}

			public function remove_cap( string $capability ): void {
				unset( $this->capabilities[ $capability ] );
			}
		}
	}
}

namespace StoreAccountant\Tests\Unit\Security\Admin {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use PHPUnit\Framework\TestCase;
	use StoreAccountant\Security\Admin\PermissionsSettingsForm;
	use StoreAccountant\Security\Permission\PermissionAction;
	use StoreAccountant\Security\Permission\PermissionActionIds;
	use StoreAccountant\Security\Permission\PermissionActionRegistry;
	use StoreAccountant\Security\Permission\RolePermissionRepository;
	use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
	use WP_Role;

	/**
	 * Tests permission settings form behavior.
	 */
	final class PermissionsSettingsFormTest extends TestCase {
		/**
		 * @var array<int, PermissionAction>
		 */
		private array $actions;

		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();

			$this->actions = [
				new PermissionAction(
					PermissionActionIds::ACCESS_ADMIN,
					'Access Accounting',
					'General',
					StoreAccountantCapabilities::ACCESS_ADMIN,
					'Open StoreAccountant admin screens.'
				),
				new PermissionAction(
					PermissionActionIds::EXPORT_CREATE,
					'Create Exports',
					'Exports',
					StoreAccountantCapabilities::CREATE_EXPORTS,
					''
				),
			];

			Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
			Functions\when( '_n' )->alias( static fn ( string $single, string $plural, int $number, string $domain = 'default' ): string => 1 === $number ? $single : $plural );
			Functions\when( 'esc_html' )->returnArg( 1 );
			Functions\when( 'esc_attr' )->returnArg( 1 );
			Functions\when( 'esc_html_e' )->alias(
				static function ( string $text, string $domain = 'default' ): void {
					echo $text;
				}
			);
			Functions\when( 'sanitize_key' )->alias(
				static fn ( string $key ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $key ) ?? '' )
			);
			Functions\when( 'sanitize_html_class' )->alias(
				static fn ( string $class ): string => preg_replace( '/[^A-Za-z0-9_\\-]/', '-', $class ) ?? ''
			);
			Functions\when( 'wp_unslash' )->returnArg( 1 );
			Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
			Functions\when( 'checked' )->alias(
				static function ( bool $checked ): void {
					if ( $checked ) {
						echo 'checked="checked"';
					}
				}
			);
			Functions\when( 'translate_user_role' )->returnArg( 1 );
			Functions\when( 'apply_filters' )->alias(
				fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
					'storeaccountant_permission_action' => $this->actions,
					'storeaccountant_assignable_permission_roles' => $value,
					default => $value,
				}
			);
			Functions\when( 'wp_roles' )->alias(
				static fn (): object => (object) [
					'roles' => [
						'administrator' => [
							'name'         => 'Administrator',
							'capabilities' => [ 'manage_options' => true ],
						],
						'shop_manager'  => [
							'name'         => 'Shop manager',
							'capabilities' => [ 'manage_woocommerce' => true ],
						],
					],
				]
			);
			Functions\when( 'get_role' )->alias(
				static fn ( string $role_id ): ?WP_Role => 'shop_manager' === $role_id
				? new WP_Role( [ StoreAccountantCapabilities::CREATE_EXPORTS => true ] )
				: new WP_Role()
			);
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_get_roles_from_request_sanitizes_roles_per_permission_action(): void {
			$roles = $this->form()->get_roles_from_request(
				[
					PermissionsSettingsForm::FIELD_NAME => [
						PermissionActionIds::ACCESS_ADMIN  => [ 'Shop Manager', '<script>', 123, '' ],
						PermissionActionIds::EXPORT_CREATE => [ 'editor', 'custom-role!' ],
						'unknown.action'                   => [ 'administrator' ],
					],
				]
			);

			self::assertSame(
				[
					PermissionActionIds::ACCESS_ADMIN  => [ 'shopmanager', 'script', '123' ],
					PermissionActionIds::EXPORT_CREATE => [ 'editor', 'custom-role' ],
				],
				$roles
			);
		}

		public function test_render_fields_groups_actions_and_marks_saved_roles(): void {
			ob_start();
			$this->form()->render_fields();
			$output = (string) ob_get_clean();

			self::assertStringContainsString( 'Permission role assignments', $output );
			self::assertStringContainsString( 'General', $output );
			self::assertStringContainsString( 'Exports', $output );
			self::assertStringContainsString( 'Access Accounting', $output );
			self::assertStringContainsString( 'Create Exports', $output );
			self::assertStringContainsString( 'value="shop_manager"', $output );
			self::assertStringContainsString( 'data-selected-roles="["shop_manager"]"', $output );
			self::assertStringContainsString( 'Administrator', $output );
		}

		private function form(): PermissionsSettingsForm {
			$registry = new PermissionActionRegistry();

			return new PermissionsSettingsForm(
				$registry,
				new RolePermissionRepository( $registry )
			);
		}
	}
}
