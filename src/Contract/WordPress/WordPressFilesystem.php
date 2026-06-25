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

namespace StoreAccountant\Contract\WordPress;

use function function_exists;
use function WP_Filesystem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small wrapper around WordPress filesystem helpers for local plugin files.
 */
final readonly class WordPressFilesystem {
	/**
	 * Writes contents through WP_Filesystem.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $path     Absolute file path.
	 * @param string $contents File contents.
	 */
	public static function put_contents( string $path, string $contents ): bool {
		$filesystem = self::get_filesystem();

		return null !== $filesystem && (bool) $filesystem->put_contents( $path, $contents );
	}

	/**
	 * Reads contents through WP_Filesystem.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $path Absolute file path.
	 *
	 * @return string|false
	 */
	public static function get_contents( string $path ): string|false {
		$filesystem = self::get_filesystem();

		return null !== $filesystem ? $filesystem->get_contents( $path ) : false;
	}

	/**
	 * Removes an empty directory through WP_Filesystem.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $path Absolute directory path.
	 */
	public static function rmdir( string $path ): bool {
		$filesystem = self::get_filesystem();

		return null !== $filesystem && (bool) $filesystem->rmdir( $path );
	}

	/**
	 * Deletes a file or directory through WP_Filesystem.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $path      Absolute file or directory path.
	 * @param bool   $recursive Whether directories should be deleted recursively.
	 * @param string $type      Optional filesystem object type, such as 'f' or 'd'.
	 */
	public static function delete( string $path, bool $recursive = false, string $type = '' ): bool {
		$filesystem = self::get_filesystem();

		return null !== $filesystem && (bool) $filesystem->delete(
			$path,
			$recursive,
			'' !== $type ? $type : false
		);
	}

	/**
	 * Gets the initialized WordPress filesystem object.
	 */
	private static function get_filesystem(): ?object {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem && true !== WP_Filesystem() ) {
			return null;
		}

		if ( ! $wp_filesystem ) {
			return null;
		}

		return $wp_filesystem;
	}
}
