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

namespace StoreAccountant\Storage;

use WP_Error;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use function __;
use function array_diff;
use function file_exists;
use function function_exists;
use function is_dir;
use function is_file;
use function scandir;
use function sprintf;
use function trailingslashit;
use function wp_delete_file;
use function wp_is_writable;
use function wp_mkdir_p;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares protected plugin-owned directories below uploads.
 */
final readonly class ProtectedUploadDirectory {
	private const HTACCESS_FILE = '.htaccess';

	private const HTACCESS_CONTENT = 'deny from all';

	private const INDEX_FILE = 'index.html';

	/**
	 * Ensures a protected writable directory exists.
	 *
	 * @param string $path         Absolute directory path.
	 * @param string $display_path Human-readable directory path.
	 *
	 * @return true|WP_Error
	 */
	public function ensure( string $path, string $display_path ): true|WP_Error {
		$this->load_file_helpers();

		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return new WP_Error(
				'storeaccountant_protected_directory_not_created',
				sprintf(
					/* translators: %s: directory path */
					__( 'StoreAccountant could not create the protected directory at %s.', 'storeaccountant' ),
					$display_path
				)
			);
		}

		if ( ! wp_is_writable( $path ) ) {
			return new WP_Error(
				'storeaccountant_protected_directory_not_writable',
				sprintf(
					/* translators: %s: directory path */
					__( 'StoreAccountant protected directory exists but is not writable: %s.', 'storeaccountant' ),
					$display_path
				)
			);
		}

		return $this->ensure_protection_files( $path, $display_path );
	}

	/**
	 * Deletes a directory when it contains only files managed by this service.
	 *
	 * @param string $path Directory path.
	 */
	public function delete_if_empty( string $path ): void {
		if ( ! is_dir( $path ) || ! $this->contains_only_managed_files( $path ) ) {
			return;
		}

		$this->delete_managed_files( $path );
		WordPressFilesystem::rmdir( $path );
	}

	/**
	 * Checks whether a directory contains only protection files.
	 *
	 * @param string $path Directory path.
	 */
	private function contains_only_managed_files( string $path ): bool {
		$items = scandir( $path );

		if ( false === $items ) {
			return false;
		}

		return [] === array_diff( $items, [ '.', '..', self::INDEX_FILE, self::HTACCESS_FILE ] );
	}

	/**
	 * Ensures files that prevent public directory browsing exist.
	 *
	 * @param string $path         Directory path.
	 * @param string $display_path Human-readable directory path.
	 *
	 * @return true|WP_Error
	 */
	private function ensure_protection_files( string $path, string $display_path ): true|WP_Error {
		$index_path    = trailingslashit( $path ) . self::INDEX_FILE;
		$htaccess_path = trailingslashit( $path ) . self::HTACCESS_FILE;

		if ( ! file_exists( $index_path ) && ! WordPressFilesystem::put_contents( $index_path, '' ) ) {
			return new WP_Error(
				'storeaccountant_index_file_not_created',
				sprintf(
					/* translators: %s: directory path */
					__( 'StoreAccountant could not create the index file in %s.', 'storeaccountant' ),
					$display_path
				)
			);
		}

		if ( ! file_exists( $htaccess_path ) && ! WordPressFilesystem::put_contents( $htaccess_path, self::HTACCESS_CONTENT ) ) {
			return new WP_Error(
				'storeaccountant_htaccess_file_not_created',
				sprintf(
					/* translators: %s: directory path */
					__( 'StoreAccountant could not create the access protection file in %s.', 'storeaccountant' ),
					$display_path
				)
			);
		}

		return true;
	}

	/**
	 * Deletes protection files.
	 *
	 * @param string $path Directory path.
	 */
	private function delete_managed_files( string $path ): void {
		foreach ( [ self::INDEX_FILE, self::HTACCESS_FILE ] as $file ) {
			$file_path = trailingslashit( $path ) . $file;

			if ( is_file( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
	}

	/**
	 * Loads WordPress filesystem helper functions.
	 */
	private function load_file_helpers(): void {
		if ( ! function_exists( 'wp_delete_file' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}
}
