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

use Symfony\Component\Finder\Finder;

$configuredBuildDirectory = getenv( 'STOREACCOUNTANT_SCOPER_BUILD_DIR' );
$buildDirectory           = is_string( $configuredBuildDirectory ) && '' !== $configuredBuildDirectory
	? $configuredBuildDirectory
	: getcwd();

if ( ! is_string( $buildDirectory ) ) {
	$buildDirectory = __DIR__;
}

$wordpressExcludesDirectory = __DIR__ . '/tools/php-scoper/vendor/sniccowp/php-scoper-wordpress-excludes/generated';
$loadWordPressExcludes     = static function ( string $fileName ) use ( $wordpressExcludesDirectory ): array {
	$content = file_get_contents( $wordpressExcludesDirectory . '/' . $fileName );

	if ( false === $content ) {
		return [];
	}

	return json_decode( $content, true, 512, JSON_THROW_ON_ERROR );
};

return [
	'prefix'                => 'StoreAccounantVendor',
	'php-version'           => '8.2',
	'output-dir'            => dirname( $buildDirectory ) . '/storeaccountant-scoped',
	'expose-global-classes' => false,
	'exclude-files'         => [
		$buildDirectory . '/storeaccountant.php',
	],
	'finders'               => [
		Finder::create()
			->files()
			->ignoreVCS( true )
			->exclude(
				[
					'.github',
					'docs',
					'test',
					'tests',
					'Tests',
				]
			)
			->notName(
				[
					'.gitattributes',
					'.gitignore',
					'composer.lock',
					'phpcs.xml',
					'phpcs.xml.dist',
					'phpstan.neon',
					'phpunit.xml',
					'phpunit.xml.dist',
					'psalm.xml',
				]
			)
			->in( $buildDirectory ),
	],
	'exclude-namespaces'    => [
		'Automattic\\WooCommerce',
		'StoreAccountant',
	],
	'exclude-classes'       => array_merge(
		$loadWordPressExcludes( 'exclude-wordpress-classes.json' ),
		[
			'ActionScheduler',
			'StoreAccountant',
			'WooCommerce',
			'/^ActionScheduler_.*/',
			'/^WC_.*/',
		]
	),
	'exclude-functions'     => array_merge(
		$loadWordPressExcludes( 'exclude-wordpress-functions.json' ),
		[
			'WC',
			'WPO_WCPDF',
			'/^as_.*/',
			'/^is_(woocommerce|shop|product.*|cart|checkout|account_page|wc_endpoint_url)$/',
			'/^wc_.*/',
			'/^wcpdf_.*/',
			'/^woocommerce_.*/',
			'/^wpo_ips_.*/',
		]
	),
	'exclude-constants'     => array_merge(
		$loadWordPressExcludes( 'exclude-wordpress-constants.json' ),
		[
			'/^WC_.*/',
			'/^WOOCOMMERCE_.*/',
		]
	),
];
