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

$storeaccountant_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $storeaccountant_tests_dir ) {
	$storeaccountant_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$storeaccountant_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $storeaccountant_phpunit_polyfills_path ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress test bootstrap expects this PHPUnit Polyfills constant name.
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $storeaccountant_phpunit_polyfills_path );
}

if ( ! file_exists( "{$storeaccountant_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$storeaccountant_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$storeaccountant_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function storeaccountant_manually_load_plugin() {
	require dirname( __DIR__ ) . '/storeaccountant.php';
}

tests_add_filter( 'muplugins_loaded', 'storeaccountant_manually_load_plugin' );

// Start up the WP testing environment.
require "{$storeaccountant_tests_dir}/includes/bootstrap.php";
