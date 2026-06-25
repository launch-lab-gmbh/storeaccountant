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

use StoreAccountant\Uninstall\PluginUninstaller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$storeaccountant_autoload_file = __DIR__ . '/vendor/scoper-autoload.php';

if ( ! file_exists( $storeaccountant_autoload_file ) ) {
	$storeaccountant_autoload_file = __DIR__ . '/vendor/autoload.php';
}

if ( ! file_exists( $storeaccountant_autoload_file ) ) {
	return;
}

require_once $storeaccountant_autoload_file;

( new PluginUninstaller() )->uninstall();
