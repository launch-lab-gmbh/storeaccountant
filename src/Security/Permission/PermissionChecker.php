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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks StoreAccountant action permissions.
 */
final readonly class PermissionChecker {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private PermissionActionRegistry $actions
	) {}

	/**
	 * Checks whether the current user can perform an action.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $action_id Permission action ID.
	 * @param mixed  ...$args   Optional context arguments.
	 */
	public function can( string $action_id, mixed ...$args ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$action = $this->actions->get( $action_id );

		if ( null === $action ) {
			return false;
		}

		if ( PermissionActionIds::ACCESS_ADMIN !== $action_id && ! current_user_can( StoreAccountantCapabilities::ACCESS_ADMIN ) ) {
			return false;
		}

		return current_user_can( $action->get_capability(), ...$args );
	}

	/**
	 * Gets the capability for a known action, falling back when the action was filtered out.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_capability( string $action_id, string $fallback ): string {
		$action = $this->actions->get( $action_id );

		return null !== $action ? $action->get_capability() : $fallback;
	}
}
