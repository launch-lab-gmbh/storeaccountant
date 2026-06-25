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
 * Registers StoreAccountant's built-in permission actions.
 */
final readonly class CorePermissionActionProvider implements HookRegistrarInterface {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter( 'storeaccountant_permission_action', [ $this, 'register_actions' ], HookRegistrarInterface::DEFAULT_PRIORITY );
	}

	/**
	 * Registers core actions.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, PermissionAction> $actions Registered actions.
	 *
	 * @return array<string, PermissionAction>
	 */
	public function register_actions( array $actions ): array {
		foreach ( $this->get_actions() as $action ) {
			$actions[ $action->get_id() ] = $action;
		}

		return $actions;
	}

	/**
	 * Gets built-in permission actions.
	 *
	 * @return array<int, PermissionAction>
	 */
	private function get_actions(): array {
		return [
			new PermissionAction(
				PermissionActionIds::ACCESS_ADMIN,
				__( 'Access StoreAccountant admin area', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::ACCESS_ADMIN,
				__( 'Allows the role to open StoreAccountant admin screens.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::MANAGE_SETTINGS,
				__( 'Manage plugin settings', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::MANAGE_SETTINGS,
				__( 'Allows changes to storage and invoice provider settings.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS,
				__( 'View download passwords', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS,
				__( 'Allows revealing stored export download passwords in the backend.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE,
				__( 'Manage diagnostic logging', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::MANAGE_DIAGNOSTICS,
				__( 'Allows enabling or disabling StoreAccountant diagnostic logging.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::DIAGNOSTIC_PACKAGE_DOWNLOAD,
				__( 'Download diagnostic packages', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::DOWNLOAD_DIAGNOSTICS,
				__( 'Allows downloading diagnostic packages created after StoreAccountant errors.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::MANAGE_PERMISSIONS,
				__( 'Manage permissions', 'storeaccountant' ),
				__( 'Administration', 'storeaccountant' ),
				StoreAccountantCapabilities::MANAGE_PERMISSIONS,
				__( 'Allows changing StoreAccountant role permissions.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_LIST,
				__( 'View export overview', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::READ_EXPORTS,
				__( 'Allows access to the accounting export list.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_VIEW,
				__( 'View export details', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::VIEW_EXPORT,
				__( 'Allows opening saved export detail views.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_CREATE,
				__( 'Create exports', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::CREATE_EXPORTS,
				__( 'Allows starting quick exports and exports from configurations.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_DOWNLOAD,
				__( 'Download export files', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::DOWNLOAD_EXPORT,
				__( 'Allows downloading generated export archives.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_VIEW_LOG,
				__( 'View export logs', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::VIEW_EXPORT_LOG,
				__( 'Allows viewing technical export logs. These logs can contain sensitive data such as server paths, exception messages, and stack traces. Grant this permission only to trusted administrators.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::EXPORT_DELETE,
				__( 'Delete exports', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				StoreAccountantCapabilities::DELETE_EXPORTS,
				__( 'Allows moving saved export records to trash or deleting them permanently.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_LIST,
				__( 'View configuration overview', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::READ_CONFIGURATIONS,
				__( 'Allows access to the export configuration list.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_VIEW,
				__( 'View configurations', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::VIEW_CONFIGURATION,
				__( 'Allows opening saved configuration detail views.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_CREATE,
				__( 'Create configurations', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::CREATE_CONFIGURATIONS,
				__( 'Allows creating reusable export configurations.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_EDIT,
				__( 'Edit configurations', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::EDIT_CONFIGURATION,
				__( 'Allows changing saved export configurations.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_DELETE,
				__( 'Delete configurations', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::DELETE_CONFIGURATIONS,
				__( 'Allows moving export configurations to trash or deleting them permanently.', 'storeaccountant' )
			),
			new PermissionAction(
				PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING,
				__( 'Edit field mappings', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				StoreAccountantCapabilities::EDIT_FIELD_MAPPING,
				__( 'Allows changing field mappings on export configurations.', 'storeaccountant' )
			),
		];
	}
}
