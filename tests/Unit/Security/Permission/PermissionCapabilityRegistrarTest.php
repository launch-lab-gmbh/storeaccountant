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
		 * Minimal WordPress role double for permission capability tests.
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

namespace StoreAccountant\Tests\Unit\Security\Permission {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use Mockery;
	use PHPUnit\Framework\TestCase;
	use StoreAccountant\Security\Permission\PermissionAction;
	use StoreAccountant\Security\Permission\PermissionActionIds;
	use StoreAccountant\Security\Permission\PermissionActionRegistry;
	use StoreAccountant\Security\Permission\PermissionCapabilityRegistrar;
	use StoreAccountant\Security\Permission\RolePermissionRepository;
	use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
	use WP_Role;

	/**
	 * Tests installation of default permission capabilities.
	 */
	final class PermissionCapabilityRegistrarTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_register_adds_init_hook(): void {
			Functions\expect( 'add_action' )
			->once()
			->with( 'init', Mockery::type( 'array' ), 20 );

			$this->registrar()->register();

			self::assertTrue( true );
		}

		public function test_ensure_defaults_adds_admin_capabilities_and_first_run_shop_manager_defaults(): void {
			$roles = [
				'administrator' => new WP_Role( [ 'manage_options' => true ] ),
				'shop_manager'  => new WP_Role( [ 'manage_woocommerce' => true ] ),
			];

			$this->mock_roles( $roles );
			$this->mock_actions();

			Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_permission_defaults_installed', '' )
			->andReturn( '' );
			Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_permission_defaults_installed', '1', false )
			->andReturn( true );
			Functions\when( 'sanitize_key' )->alias(
				static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
			);

			$this->registrar()->ensure_defaults();

			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ) );
			self::assertTrue( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
			self::assertFalse( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ) );
		}

		public function test_ensure_defaults_skips_first_run_defaults_when_option_is_installed(): void {
			$roles = [
				'administrator' => new WP_Role( [ 'manage_options' => true ] ),
				'shop_manager'  => new WP_Role( [ 'manage_woocommerce' => true ] ),
			];

			$this->mock_roles( $roles );
			$this->mock_actions();

			Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_permission_defaults_installed', '' )
			->andReturn( '1' );
			Functions\expect( 'update_option' )->never();

			$this->registrar()->ensure_defaults();

			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
			self::assertFalse( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertFalse( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
		}

		/**
		 * @param array<string, WP_Role> $roles Role objects keyed by role ID.
		 */
		private function mock_roles( array $roles ): void {
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
			Functions\when( 'translate_user_role' )->alias( static fn ( string $role ): string => $role );
			Functions\when( 'get_role' )->alias( static fn ( string $role_id ): mixed => $roles[ $role_id ] ?? null );
		}

		private function mock_actions(): void {
			$actions = [
				new PermissionAction( PermissionActionIds::ACCESS_ADMIN, 'Access admin', 'Test', StoreAccountantCapabilities::ACCESS_ADMIN ),
				new PermissionAction( PermissionActionIds::EXPORT_VIEW, 'View exports', 'Test', StoreAccountantCapabilities::VIEW_EXPORT ),
				new PermissionAction(
					PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS,
					'View download passwords',
					'Test',
					StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS
				),
			];

			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value ) use ( $actions ): mixed {
					return match ( $hook ) {
						'storeaccountant_permission_action' => $actions,
						'storeaccountant_assignable_permission_roles' => $value,
						default => $value,
					};
				}
			);
		}

		private function registrar(): PermissionCapabilityRegistrar {
			return new PermissionCapabilityRegistrar(
				new RolePermissionRepository( new PermissionActionRegistry() )
			);
		}
	}
}
