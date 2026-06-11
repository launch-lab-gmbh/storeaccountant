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

namespace StoreAccountant\Security\Permission;

use WP_Role;
use StoreAccountant\Security\Permission\Contract\PermissionActionInterface;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function sanitize_key;
use function translate_user_role;
use function wp_roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes StoreAccountant capabilities on WordPress roles.
 */
final readonly class RolePermissionRepository {
	public const ADMINISTRATOR_ROLE = 'administrator';

	private const ADMIN_ROLE_CAPABILITIES = [
		'manage_options',
		'manage_woocommerce',
		'edit_posts',
		'read_private_posts',
		'list_users',
		StoreAccountantCapabilities::ACCESS_ADMIN,
	];

	public function __construct(
		private PermissionActionRegistry $actions
	) {}

	/**
	 * Gets roles that can be assigned StoreAccountant permissions.
	 *
	 * @return array<string, array{label: string, locked: bool}>
	 */
	public function get_assignable_roles(): array {
		$wp_roles = wp_roles();
		$roles    = [];

		foreach ( $wp_roles->roles as $role_id => $role ) {
			if ( ! $this->is_assignable_role( $role_id, $role['capabilities'] ?? [] ) ) {
				continue;
			}

			$roles[ $role_id ] = [
				'label'  => translate_user_role( $role['name'] ),
				'locked' => self::ADMINISTRATOR_ROLE === $role_id,
			];
		}

		/**
		 * Filters roles shown in the StoreAccountant permissions UI.
		 *
		 * Roles should only be added when they are intended to access wp-admin.
		 *
		 * @param array<string, array{label: string, locked: bool}> $roles Assignable roles keyed by role ID.
		 */
		$roles = apply_filters( 'storeaccountant_assignable_permission_roles', $roles );

		if ( ! array_key_exists( self::ADMINISTRATOR_ROLE, $roles ) && isset( $wp_roles->roles[ self::ADMINISTRATOR_ROLE ] ) ) {
			$roles = [
				self::ADMINISTRATOR_ROLE => [
					'label'  => translate_user_role( $wp_roles->roles[ self::ADMINISTRATOR_ROLE ]['name'] ),
					'locked' => true,
				],
				...$roles,
			];
		}

		return $roles;
	}

	/**
	 * Gets assignable role options for token fields, excluding locked roles.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	public function get_role_options(): array {
		$options = [];

		foreach ( $this->get_assignable_roles() as $role_id => $role ) {
			if ( $role['locked'] ) {
				continue;
			}

			$options[] = [
				'value' => $role_id,
				'label' => $role['label'],
			];
		}

		return $options;
	}

	/**
	 * Gets role IDs that have a permission action.
	 *
	 * @return array<int, string>
	 */
	public function get_roles_for_action( PermissionActionInterface $action, bool $include_locked = true ): array {
		$roles = [];

		foreach ( $this->get_assignable_roles() as $role_id => $role_data ) {
			if ( ! $include_locked && $role_data['locked'] ) {
				continue;
			}

			$role = get_role( $role_id );

			if ( $role instanceof WP_Role && $role->has_cap( $action->get_capability() ) ) {
				$roles[] = $role_id;
			}
		}

		if ( $include_locked && ! in_array( self::ADMINISTRATOR_ROLE, $roles, true ) ) {
			$roles[] = self::ADMINISTRATOR_ROLE;
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Saves selected roles for each action.
	 *
	 * @param array<string, array<int, string>> $role_ids_by_action Role IDs keyed by action ID.
	 */
	public function save( array $role_ids_by_action ): void {
		$assignable_roles = $this->get_assignable_roles();
		$allowed_role_ids = array_keys( $assignable_roles );

		foreach ( $this->actions->get_all() as $action ) {
			$selected_role_ids = $this->sanitize_role_ids( $role_ids_by_action[ $action->get_id() ] ?? [], $allowed_role_ids );

			if ( PermissionActionIds::ACCESS_ADMIN !== $action->get_id() ) {
				$selected_role_ids[] = self::ADMINISTRATOR_ROLE;
			}

			$this->sync_action_capability( $action, $selected_role_ids, $assignable_roles );
		}

		$this->sync_access_capability();
	}

	/**
	 * Ensures administrator has all registered StoreAccountant capabilities.
	 */
	public function ensure_administrator_capabilities(): void {
		$administrator = get_role( self::ADMINISTRATOR_ROLE );

		if ( ! $administrator instanceof WP_Role ) {
			return;
		}

		foreach ( $this->actions->get_all() as $action ) {
			$administrator->add_cap( $action->get_capability() );
		}
	}

	/**
	 * Checks whether a role should be offered in the permissions UI.
	 *
	 * @param string              $role_id      Role ID.
	 * @param array<string, bool> $capabilities Role capabilities.
	 */
	private function is_assignable_role( string $role_id, array $capabilities ): bool {
		if ( self::ADMINISTRATOR_ROLE === $role_id ) {
			return true;
		}

		foreach ( self::ADMIN_ROLE_CAPABILITIES as $capability ) {
			if ( ! empty( $capabilities[ $capability ] ) ) {
				return true;
			}
		}

		foreach ( $this->actions->get_all() as $action ) {
			if ( ! empty( $capabilities[ $action->get_capability() ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Sanitizes role IDs against allowed assignable roles.
	 *
	 * @param array<int, string> $role_ids         Submitted role IDs.
	 * @param array<int, string> $allowed_role_ids Allowed role IDs.
	 *
	 * @return array<int, string>
	 */
	private function sanitize_role_ids( array $role_ids, array $allowed_role_ids ): array {
		$role_ids = array_map( static fn ( string $role_id ): string => sanitize_key( $role_id ), $role_ids );
		$role_ids = array_filter(
			$role_ids,
			static fn ( string $role_id ): bool => in_array( $role_id, $allowed_role_ids, true ) && self::ADMINISTRATOR_ROLE !== $role_id
		);

		return array_values( array_unique( $role_ids ) );
	}

	/**
	 * Adds or removes one action capability on assignable roles.
	 *
	 * @param array<int, string>                                $selected_role_ids Selected role IDs.
	 * @param array<string, array{label: string, locked: bool}> $assignable_roles  Assignable role metadata.
	 */
	private function sync_action_capability( PermissionActionInterface $action, array $selected_role_ids, array $assignable_roles ): void {
		foreach ( $assignable_roles as $role_id => $role_data ) {
			$role = get_role( $role_id );

			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			if ( $role_data['locked'] || in_array( $role_id, $selected_role_ids, true ) ) {
				$role->add_cap( $action->get_capability() );
				continue;
			}

			$role->remove_cap( $action->get_capability() );
		}
	}

	/**
	 * Grants admin access to every role with at least one StoreAccountant action.
	 */
	private function sync_access_capability(): void {
		foreach ( $this->get_assignable_roles() as $role_id => $role_data ) {
			$role = get_role( $role_id );

			if ( ! $role instanceof WP_Role ) {
				continue;
			}

			if ( $role_data['locked'] || $this->role_has_storeaccountant_action( $role ) ) {
				$role->add_cap( StoreAccountantCapabilities::ACCESS_ADMIN );
				continue;
			}

			$role->remove_cap( StoreAccountantCapabilities::ACCESS_ADMIN );
		}
	}

	/**
	 * Checks whether a role has any StoreAccountant action capability except admin access.
	 */
	private function role_has_storeaccountant_action( WP_Role $role ): bool {
		foreach ( $this->actions->get_all() as $action ) {
			if ( PermissionActionIds::ACCESS_ADMIN === $action->get_id() ) {
				continue;
			}

			if ( $role->has_cap( $action->get_capability() ) ) {
				return true;
			}
		}

		return false;
	}
}
