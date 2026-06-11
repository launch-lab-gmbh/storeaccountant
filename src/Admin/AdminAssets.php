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

namespace StoreAccountant\Admin;

use StoreAccountant as StoreAccountantPlugin;
use StoreAccountant\Export\Admin\AccountingExportPage;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use function add_action;
use function admin_url;
use function file_exists;
use function filemtime;
use function in_array;
use function plugin_dir_path;
use function plugins_url;
use function wp_add_inline_script;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues StoreAccountant admin assets.
 */
final readonly class AdminAssets implements HookRegistrarInterface {
	private const STYLE_HANDLE                      = 'storeaccountant-admin';
	private const SCRIPT_HANDLE                     = 'storeaccountant-export-form';
	private const EXPORT_LIST_POLLING_SCRIPT_HANDLE = 'storeaccountant-export-list-polling';

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueues assets for StoreAccountant admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( ! $this->is_storeaccountant_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/css/admin.css', STOREACCOUNTANT_FILE ),
			[],
			$this->get_asset_version( 'assets/css/admin.css' )
		);

		if ( $this->uses_react_widgets( $hook_suffix ) ) {
			wp_enqueue_style( 'wp-components' );
		}

		if ( $this->uses_export_form_script( $hook_suffix ) ) {
			wp_enqueue_script(
				self::SCRIPT_HANDLE,
				plugins_url( 'assets/js/export-form.js', STOREACCOUNTANT_FILE ),
				$this->uses_react_widgets( $hook_suffix ) ? [ 'wp-components', 'wp-element' ] : [],
				$this->get_asset_version( 'assets/js/export-form.js' ),
				true
			);
		}

		if ( $this->is_export_list_screen( $hook_suffix ) ) {
			wp_enqueue_script(
				self::EXPORT_LIST_POLLING_SCRIPT_HANDLE,
				plugins_url( 'assets/js/export-list-polling.js', STOREACCOUNTANT_FILE ),
				[],
				$this->get_asset_version( 'assets/js/export-list-polling.js' ),
				true
			);

			wp_add_inline_script(
				self::EXPORT_LIST_POLLING_SCRIPT_HANDLE,
				'window.storeAccountantExportPolling = ' . wp_json_encode(
					[
						'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
						'nonce'      => wp_create_nonce( 'storeaccountant_poll_exports' ),
						'intervalMs' => 3000,
						'backoffMs'  => 8000,
					]
				) . ';',
				'before'
			);
		}
	}

	/**
	 * Gets an asset version that changes when the file changes.
	 *
	 * @param string $relative_path Asset path relative to the plugin root.
	 */
	private function get_asset_version( string $relative_path ): string {
		$path     = plugin_dir_path( STOREACCOUNTANT_FILE ) . $relative_path;
		$modified = file_exists( $path ) ? filemtime( $path ) : false;

		if ( false === $modified ) {
			return StoreAccountantPlugin::PLUGIN_VERSION;
		}

		return StoreAccountantPlugin::PLUGIN_VERSION . '-' . (string) $modified;
	}

	/**
	 * Checks whether the current screen belongs to StoreAccountant.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function is_storeaccountant_screen( string $hook_suffix ): bool {
		return ( 'edit.php' === $hook_suffix && in_array( $this->get_current_post_type(), [ ExportPostType::POST_TYPE, ExportConfigurationPostType::POST_TYPE ], true ) )
			|| $this->is_plugin_page( AccountingExportPage::PAGE_SLUG )
			|| $this->is_plugin_page( 'storeaccountant-export' )
			|| $this->is_plugin_page( 'storeaccountant-export-configuration' )
			|| $this->is_plugin_page( 'storeaccountant-settings' );
	}

	/**
	 * Checks whether the current screen is the native export list.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function is_export_list_screen( string $hook_suffix ): bool {
		return 'edit.php' === $hook_suffix
			&& ExportPostType::POST_TYPE === $this->get_current_post_type();
	}

	/**
	 * Checks whether the current screen renders the export form.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function uses_react_widgets( string $hook_suffix ): bool {
		return $this->is_plugin_page( AccountingExportPage::PAGE_SLUG )
			|| $this->is_plugin_page( 'storeaccountant-export-configuration' )
			|| $this->is_plugin_page( 'storeaccountant-settings' );
	}

	/**
	 * Checks whether the current admin screen needs the export form script.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	private function uses_export_form_script( string $hook_suffix ): bool {
		return $this->uses_react_widgets( $hook_suffix )
			|| $this->is_export_list_screen( $hook_suffix );
	}

	/**
	 * Checks whether the current admin request renders a plugin page.
	 *
	 * @param string $page Plugin page slug.
	 */
	private function is_plugin_page( string $page ): bool {
		return $page === $this->get_current_page();
	}

	/**
	 * Gets the current admin post type routing parameter.
	 */
	private function get_current_post_type(): string {
		return Request::get_key( 'post_type' );
	}

	/**
	 * Gets the current admin page routing parameter.
	 */
	private function get_current_page(): string {
		return Request::get_key( 'page' );
	}
}
