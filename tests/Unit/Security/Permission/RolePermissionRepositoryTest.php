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
		 * Minimal WordPress role double for permission repository unit tests.
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
	use PHPUnit\Framework\TestCase;
	use StoreAccountant\Security\Permission\PermissionAction;
	use StoreAccountant\Security\Permission\PermissionActionIds;
	use StoreAccountant\Security\Permission\PermissionActionRegistry;
	use StoreAccountant\Security\Permission\RolePermissionRepository;
	use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
	use WP_Role;

	/**
	 * Tests role permission persistence and normalization.
	 */
	final class RolePermissionRepositoryTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_get_assignable_roles_filters_backend_relevant_roles_and_locks_administrator(): void {
			$this->mock_actions();
			$this->mock_wp_roles(
				[
					'administrator' => [
						'name'         => 'Administrator',
						'capabilities' => [ 'manage_options' => true ],
					],
					'shop_manager'  => [
						'name'         => 'Shop manager',
						'capabilities' => [ 'manage_woocommerce' => true ],
					],
					'subscriber'    => [
						'name'         => 'Subscriber',
						'capabilities' => [ 'read' => true ],
					],
				]
			);

			$roles = $this->repository()->get_assignable_roles();

			self::assertSame(
				[
					'administrator' => [
						'label'  => 'Administrator',
						'locked' => true,
					],
					'shop_manager'  => [
						'label'  => 'Shop manager',
						'locked' => false,
					],
				],
				$roles
			);
		}

		public function test_get_role_options_excludes_locked_roles(): void {
			$this->mock_actions();
			$this->mock_wp_roles(
				[
					'administrator' => [
						'name'         => 'Administrator',
						'capabilities' => [ 'manage_options' => true ],
					],
					'editor'        => [
						'name'         => 'Editor',
						'capabilities' => [ 'edit_posts' => true ],
					],
				]
			);

			self::assertSame(
				[
					[
						'value' => 'editor',
						'label' => 'Editor',
					],
				],
				$this->repository()->get_role_options()
			);
		}

		public function test_get_roles_for_action_returns_valid_roles_and_locked_administrator(): void {
			$action = $this->action( PermissionActionIds::EXPORT_VIEW, StoreAccountantCapabilities::VIEW_EXPORT );
			$roles  = [
				'administrator' => new WP_Role(),
				'shop_manager'  => new WP_Role( [ StoreAccountantCapabilities::VIEW_EXPORT => true ] ),
				'editor'        => new WP_Role(),
			];

			$this->mock_actions( [ $action ] );
			$this->mock_wp_roles(
				[
					'administrator' => [
						'name'         => 'Administrator',
						'capabilities' => [ 'manage_options' => true ],
					],
					'shop_manager'  => [
						'name'         => 'Shop manager',
						'capabilities' => [ 'manage_woocommerce' => true ],
					],
					'editor'        => [
						'name'         => 'Editor',
						'capabilities' => [ 'edit_posts' => true ],
					],
				]
			);
			Functions\when( 'get_role' )->alias( static fn ( string $role_id ): mixed => $roles[ $role_id ] ?? null );

			$repository = $this->repository();

			self::assertSame( [ 'shop_manager', 'administrator' ], $repository->get_roles_for_action( $action ) );
			self::assertSame( [ 'shop_manager' ], $repository->get_roles_for_action( $action, false ) );
		}

		public function test_save_syncs_action_capabilities_and_access_capability(): void {
			$access_action = $this->action( PermissionActionIds::ACCESS_ADMIN, StoreAccountantCapabilities::ACCESS_ADMIN );
			$view_action   = $this->action( PermissionActionIds::EXPORT_VIEW, StoreAccountantCapabilities::VIEW_EXPORT );
			$roles         = [
				'administrator' => new WP_Role(),
				'shop_manager'  => new WP_Role(),
				'editor'        => new WP_Role( [ StoreAccountantCapabilities::VIEW_EXPORT => true ] ),
			];

			$this->mock_actions( [ $access_action, $view_action ] );
			$this->mock_wp_roles(
				[
					'administrator' => [
						'name'         => 'Administrator',
						'capabilities' => [ 'manage_options' => true ],
					],
					'shop_manager'  => [
						'name'         => 'Shop manager',
						'capabilities' => [ 'manage_woocommerce' => true ],
					],
					'editor'        => [
						'name'         => 'Editor',
						'capabilities' => [ 'edit_posts' => true ],
					],
				]
			);
			Functions\when( 'get_role' )->alias( static fn ( string $role_id ): mixed => $roles[ $role_id ] ?? null );
			Functions\when( 'sanitize_key' )->alias(
				static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
			);

			$this->repository()->save(
				[
					PermissionActionIds::ACCESS_ADMIN => [ 'shop_manager', 'missing', 'shop_manager' ],
					PermissionActionIds::EXPORT_VIEW  => [ 'shop_manager' ],
				]
			);

			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $roles['administrator']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
			self::assertTrue( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $roles['shop_manager']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
			self::assertFalse( $roles['editor']->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertFalse( $roles['editor']->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
		}

		public function test_ensure_administrator_capabilities_adds_all_registered_action_capabilities(): void {
			$administrator = new WP_Role();

			$this->mock_actions();
			Functions\when( 'get_role' )->alias(
				static fn ( string $role_id ): mixed => RolePermissionRepository::ADMINISTRATOR_ROLE === $role_id ? $administrator : null
			);

			$this->repository()->ensure_administrator_capabilities();

			self::assertTrue( $administrator->has_cap( StoreAccountantCapabilities::ACCESS_ADMIN ) );
			self::assertTrue( $administrator->has_cap( StoreAccountantCapabilities::VIEW_EXPORT ) );
		}

		/**
		 * @param array<string, array{name: string, capabilities: array<string, bool>}> $roles WordPress role data.
		 */
		private function mock_wp_roles( array $roles ): void {
			Functions\when( 'wp_roles' )->alias(
				static fn (): object => (object) [
					'roles' => $roles,
				]
			);
			Functions\when( 'translate_user_role' )->alias( static fn ( string $role ): string => $role );
		}

		/**
		 * @param array<int, PermissionAction> $actions Permission actions.
		 */
		private function mock_actions( ?array $actions = null ): void {
			$actions ??= [
				$this->action( PermissionActionIds::ACCESS_ADMIN, StoreAccountantCapabilities::ACCESS_ADMIN ),
				$this->action( PermissionActionIds::EXPORT_VIEW, StoreAccountantCapabilities::VIEW_EXPORT ),
			];

			Functions\when( 'apply_filters' )->alias(
				static function ( string $hook, mixed $value ) use ( $actions ): mixed {
					if ( 'storeaccountant_permission_action' === $hook ) {
						return $actions;
					}

					return $value;
				}
			);
		}

		private function repository(): RolePermissionRepository {
			return new RolePermissionRepository( new PermissionActionRegistry() );
		}

		private function action( string $id, string $capability ): PermissionAction {
			return new PermissionAction( $id, $id, 'Test', $capability );
		}
	}
}
