<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Uninstall;

use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;
use function as_unschedule_all_actions;
use function array_keys;
use function delete_option;
use function delete_transient;
use function function_exists;
use function str_starts_with;
use function wp_clear_scheduled_hook;
use function wp_roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes StoreAccountant plugin settings and technical database state.
 */
final readonly class PluginSettingsCleanupTask implements UninstallCleanupTaskInterface {
	/**
	 * StoreAccountant cron hook for stale export queue cleanup.
	 */
	private const QUEUE_CLEANUP_HOOK = 'storeaccountant_cleanup_export_queue';

	/**
	 * StoreAccountant Action Scheduler hooks.
	 *
	 * Duplicated here intentionally so uninstall cleanup does not have to load Symfony-backed queue transport classes.
	 *
	 * @var array<int, string>
	 */
	private const ACTION_SCHEDULER_HOOKS = [
		'storeaccountant_export_queue_start',
		'storeaccountant_export_queue_process_batch',
		'storeaccountant_export_queue_finalize',
		'storeaccountant_export_queue_finalize_attachments',
		'storeaccountant_queue_message',
	];

	/**
	 * StoreAccountant option names that should not survive uninstall.
	 *
	 * @var array<int, string>
	 */
	private const OPTIONS = [
		'storeaccountant_download_password',
		'storeaccountant_download_password_hash',
		'storeaccountant_enabled_invoice_plugin',
		'storeaccountant_enabled_storage_adapters',
		'storeaccountant_export_writer',
		'storeaccountant_queue_transport_provider',
		'storeaccountant_diagnostic_logging_enabled',
		'storeaccountant_permission_defaults_installed',
	];

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return 'plugin_settings';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function cleanup(): void {
		$this->delete_options();
		$this->delete_transients();
		$this->delete_scheduled_queue_state();
		$this->delete_role_capabilities();
	}

	/**
	 * Deletes known StoreAccountant options.
	 */
	private function delete_options(): void {
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Deletes known StoreAccountant transients.
	 */
	private function delete_transients(): void {
		delete_transient( 'storeaccountant_storage_activation_error' );
	}

	/**
	 * Deletes queued StoreAccountant cron and Action Scheduler records.
	 */
	private function delete_scheduled_queue_state(): void {
		wp_clear_scheduled_hook( self::QUEUE_CLEANUP_HOOK );

		foreach ( self::ACTION_SCHEDULER_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );

			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, null, 'storeaccountant' );
			}
		}
	}

	/**
	 * Removes StoreAccountant capabilities from WordPress roles.
	 */
	private function delete_role_capabilities(): void {
		$roles = wp_roles();

		foreach ( $roles->role_objects as $role ) {
			foreach ( array_keys( $role->capabilities ) as $capability ) {
				if ( str_starts_with( (string) $capability, 'storeaccountant_' ) ) {
					$role->remove_cap( (string) $capability );
				}
			}
		}
	}
}
