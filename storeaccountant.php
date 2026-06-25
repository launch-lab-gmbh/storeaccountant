<?php
/**
 * Plugin Name: StoreAccountant
 * Plugin URI: https://storeaccountant.launch-lab.de/
 * Description: Accounting workflow plugin for WooCommerce.
 * Version: 0.5.9
 * Author: LaunchLab GmbH
 * Author URI: https://launch-lab.de
 * Text Domain: storeaccountant
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * WC tested up to: 10.7
 *
 * @package StoreAccountant
 */

declare(strict_types=1);

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use StoreAccountant\Plugin;
use StoreAccountant\PluginActivator;
use StoreAccountant\PluginDeactivator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'STOREACCOUNTANT_FILE' ) ) {
	define( 'STOREACCOUNTANT_FILE', __FILE__ );
}

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Shared plugin metadata.
 */
final readonly class StoreAccountant {
	/**
	 * Current plugin version.
	 */
	public const PLUGIN_VERSION = '0.5.9';

	/**
	 * Minimum supported PHP version.
	 */
	public const PHP_VERSION = '8.2';
}

$storeaccountant_autoload_file = __DIR__ . '/vendor/scoper-autoload.php';

if ( ! file_exists( $storeaccountant_autoload_file ) ) {
	$storeaccountant_autoload_file = __DIR__ . '/vendor/autoload.php';
}

require_once $storeaccountant_autoload_file;

register_activation_hook( __FILE__, [ PluginActivator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ PluginDeactivator::class, 'deactivate' ] );

( new Plugin() )->boot();
