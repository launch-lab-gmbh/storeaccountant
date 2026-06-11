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

use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation cleanup.
 */
final readonly class PluginDeactivator {
	/**
	 * Runs deactivation cleanup.
	 */
	public static function deactivate(): void {
		$configuration = LocalStorageConfiguration::from_wordpress_uploads();

		if ( is_wp_error( $configuration ) ) {
			return;
		}

		( new LocalStorageAdapter( $configuration ) )->delete_if_empty();
	}
}
