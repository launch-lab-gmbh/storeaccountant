<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright  LaunchLab GmbH
 * @author     thomas.baier@launch-lab.de
 * @author-uri https://launch-lab.de
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Security\Permission;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines core StoreAccountant permission action identifiers.
 */
final class PermissionActionIds {

	public const ACCESS_ADMIN                     = 'admin.access';
	public const MANAGE_SETTINGS                  = 'settings.manage';
	public const VIEW_DOWNLOAD_PASSWORDS          = 'settings.view_download_passwords';
	public const DIAGNOSTIC_LOGGING_MANAGE        = 'settings.diagnostic_logging_manage';
	public const DIAGNOSTIC_PACKAGE_DOWNLOAD      = 'settings.diagnostic_package_download';
	public const MANAGE_PERMISSIONS               = 'permissions.manage';
	public const EXPORT_LIST                      = 'export.list';
	public const EXPORT_VIEW                      = 'export.view';
	public const EXPORT_CREATE                    = 'export.create';
	public const EXPORT_DOWNLOAD                  = 'export.download';
	public const EXPORT_VIEW_LOG                  = 'export.view_log';
	public const EXPORT_DELETE                    = 'export.delete';
	public const CONFIGURATION_LIST               = 'configuration.list';
	public const CONFIGURATION_VIEW               = 'configuration.view';
	public const CONFIGURATION_CREATE             = 'configuration.create';
	public const CONFIGURATION_EDIT               = 'configuration.edit';
	public const CONFIGURATION_DELETE             = 'configuration.delete';
	public const CONFIGURATION_EDIT_FIELD_MAPPING = 'configuration.edit_field_mapping';

	private function __construct() {
	}
}
