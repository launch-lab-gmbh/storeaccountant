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

use StoreAccountant\Contract\HookRegistrarInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensures default WordPress role capabilities exist.
 */
final readonly class PermissionCapabilityRegistrar implements HookRegistrarInterface {
	private const DEFAULTS_INSTALLED_OPTION = 'storeaccountant_permission_defaults_installed';

	public function __construct(
		private RolePermissionRepository $roles
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'ensure_defaults' ], 20 );
	}

	/**
	 * Ensures administrator caps and first-run defaults for shop managers.
	 */
	public function ensure_defaults(): void {
		$this->roles->ensure_administrator_capabilities();

		if ( '1' === (string) get_option( self::DEFAULTS_INSTALLED_OPTION, '' ) ) {
			return;
		}

		$this->roles->save(
			[
				PermissionActionIds::ACCESS_ADMIN         => [ 'shop_manager' ],
				PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS => [],
				PermissionActionIds::EXPORT_LIST          => [ 'shop_manager' ],
				PermissionActionIds::EXPORT_VIEW          => [ 'shop_manager' ],
				PermissionActionIds::EXPORT_CREATE        => [ 'shop_manager' ],
				PermissionActionIds::EXPORT_DOWNLOAD      => [ 'shop_manager' ],
				PermissionActionIds::EXPORT_DELETE        => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_LIST   => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_VIEW   => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_CREATE => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_EDIT   => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_DELETE => [ 'shop_manager' ],
				PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING => [ 'shop_manager' ],
			]
		);

		update_option( self::DEFAULTS_INSTALLED_OPTION, '1', false );
	}
}
