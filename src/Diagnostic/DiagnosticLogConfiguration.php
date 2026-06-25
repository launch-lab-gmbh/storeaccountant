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

namespace StoreAccountant\Diagnostic;

use WP_Error;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use function __;
use function trailingslashit;
use function wp_upload_dir;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configures diagnostic incident log paths.
 */
final readonly class DiagnosticLogConfiguration {
	public const RELATIVE_PATH = LocalStorageConfiguration::RELATIVE_PATH . '/logging';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		public string $root_path,
		public string $display_root_path
	) {}

	/**
	 * Builds the default diagnostic log configuration from WordPress uploads.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return self|WP_Error
	 */
	public static function from_wordpress_uploads(): self|WP_Error {
		$upload_dir = wp_upload_dir( null, false );

		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error(
				'storeaccountant_upload_directory_unavailable',
				(string) $upload_dir['error']
			);
		}

		return new self(
			trailingslashit( (string) $upload_dir['basedir'] ) . self::RELATIVE_PATH,
			'wp-content/uploads/' . self::RELATIVE_PATH
		);
	}

	/**
	 * Gets the configured diagnostic log root path.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return string|WP_Error
	 */
	public function get_root_path(): string|WP_Error {
		if ( '' === $this->root_path ) {
			return new WP_Error(
				'storeaccountant_diagnostic_log_root_missing',
				__( 'StoreAccountant diagnostic log directory is not configured.', 'storeaccountant' )
			);
		}

		return $this->root_path;
	}
}
