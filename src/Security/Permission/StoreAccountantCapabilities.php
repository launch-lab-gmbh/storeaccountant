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
 * Defines core WordPress capabilities used by StoreAccountant.
 */
final class StoreAccountantCapabilities {

	public const ACCESS_ADMIN            = 'storeaccountant_access_admin';
	public const MANAGE_SETTINGS         = 'storeaccountant_manage_settings';
	public const VIEW_DOWNLOAD_PASSWORDS = 'storeaccountant_view_download_passwords';
	public const MANAGE_DIAGNOSTICS      = 'storeaccountant_manage_diagnostics';
	public const DOWNLOAD_DIAGNOSTICS    = 'storeaccountant_download_diagnostics';
	public const MANAGE_PERMISSIONS      = 'storeaccountant_manage_permissions';
	public const READ_EXPORTS            = 'storeaccountant_read_exports';
	public const CREATE_EXPORTS          = 'storeaccountant_create_exports';
	public const VIEW_EXPORT             = 'storeaccountant_view_export';
	public const DOWNLOAD_EXPORT         = 'storeaccountant_download_export';
	public const VIEW_EXPORT_LOG         = 'storeaccountant_view_export_log';
	public const DELETE_EXPORTS          = 'storeaccountant_delete_exports';
	public const READ_CONFIGURATIONS     = 'storeaccountant_read_configurations';
	public const CREATE_CONFIGURATIONS   = 'storeaccountant_create_configurations';
	public const VIEW_CONFIGURATION      = 'storeaccountant_view_configuration';
	public const EDIT_CONFIGURATION      = 'storeaccountant_edit_configuration';
	public const DELETE_CONFIGURATIONS   = 'storeaccountant_delete_configurations';
	public const EDIT_FIELD_MAPPING      = 'storeaccountant_edit_field_mapping';

	private function __construct() {
	}
}
