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

namespace StoreAccountant\Storage\Adapter;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configures the local storage adapter paths.
 */
final readonly class LocalStorageConfiguration {
	public const RELATIVE_PATH = 'storeaccountant';

	public function __construct(
		public string $root_path,
		public string $display_root_path
	) {}

	/**
	 * Builds the default local storage configuration from WordPress uploads.
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
	 * Gets the configured local storage root path.
	 *
	 * @return string|WP_Error
	 */
	public function get_root_path(): string|WP_Error {
		if ( '' === $this->root_path ) {
			return new WP_Error(
				'storeaccountant_local_storage_root_missing',
				__( 'StoreAccountant local storage root is not configured.', 'storeaccountant' )
			);
		}

		return $this->root_path;
	}

	/**
	 * Gets the absolute zip archive path.
	 *
	 * @param string $archive_file Relative archive file path.
	 *
	 * @return string|WP_Error
	 */
	public function get_archive_path( string $archive_file ): string|WP_Error {
		$root_path = $this->get_root_path();

		if ( is_wp_error( $root_path ) ) {
			return $root_path;
		}

		if ( '' === $archive_file ) {
			return trailingslashit( $root_path );
		}

		return trailingslashit( $root_path ) . $archive_file;
	}
}
