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

namespace StoreAccountant;

use StoreAccountant as StoreAccountantPlugin;
use StoreAccountant\Storage\Admin\StorageActivationNotice;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use function add_rewrite_rule;
use function flush_rewrite_rules;
use function function_exists;
use function sprintf;
use function version_compare;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
final readonly class PluginActivator {
	/**
	 * Runs activation checks.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, StoreAccountantPlugin::PHP_VERSION, '<' ) ) {
			self::deactivate_plugin();

			wp_die(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					esc_html__( 'StoreAccountant requires PHP %1$s or higher. Your server is running PHP %2$s.', 'storeaccountant' ),
					esc_html( StoreAccountantPlugin::PHP_VERSION ),
					esc_html( PHP_VERSION )
				),
				esc_html__( 'StoreAccountant activation failed', 'storeaccountant' ),
				[
					'back_link' => true,
				]
			);
		}

		$directory     = new ProtectedUploadDirectory();
		$configuration = LocalStorageConfiguration::from_wordpress_uploads();
		$result        = is_wp_error( $configuration ) ? $configuration : ( new LocalStorageAdapter( $configuration, $directory ) )->ensure();

		if ( is_wp_error( $result ) ) {
			set_transient(
				StorageActivationNotice::TRANSIENT_NAME,
				__( 'StoreAccountant was activated, but the local storage location could not be prepared. Please check the upload directory permissions.', 'storeaccountant' ),
				60
			);
		}

		$diagnostic_configuration = DiagnosticLogConfiguration::from_wordpress_uploads();
		$diagnostic_result        = is_wp_error( $diagnostic_configuration )
			? $diagnostic_configuration
			: $directory->ensure( $diagnostic_configuration->root_path, $diagnostic_configuration->display_root_path );

		if ( is_wp_error( $diagnostic_result ) ) {
			set_transient(
				StorageActivationNotice::TRANSIENT_NAME,
				__( 'StoreAccountant was activated, but the diagnostic log directory could not be prepared. Please check the upload directory permissions.', 'storeaccountant' ),
				60
			);
		}

		( new DownloadPasswordManager( new ReversibleCrypto() ) )->ensure_global_password();

		add_rewrite_rule(
			'^storeaccountant/export-download/([^/]+)/?$',
			'index.php?storeaccountant_export_download=$matches[1]',
			'top'
		);
		flush_rewrite_rules();
	}

	/**
	 * Deactivates the plugin during a failed activation attempt.
	 */
	private static function deactivate_plugin(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( plugin_basename( STOREACCOUNTANT_FILE ) );
	}
}
