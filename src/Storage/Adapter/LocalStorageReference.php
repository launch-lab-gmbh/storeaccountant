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

use function array_filter;
use function array_map;
use function explode;
use function implode;
use function sanitize_file_name;
use function sanitize_key;
use function str_contains;
use function str_replace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a local zip archive storage reference.
 */
final readonly class LocalStorageReference {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		public string $archive_file,
		public string $path
	) {}

	/**
	 * Builds a local storage reference from a persisted storage path.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $storage_path Persisted storage path.
	 */
	public static function from_storage_path( string $storage_path ): self {
		if ( '' === $storage_path ) {
			return new self( '', '' );
		}

		return new self(
			self::sanitize_relative_path( $storage_path ),
			''
		);
	}

	/**
	 * Builds a local storage reference for a file inside an archive.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $archive_file Relative archive file path.
	 * @param string $path         Relative path inside the archive.
	 */
	public static function for_archive_file( string $archive_file, string $path ): self {
		return new self(
			self::sanitize_relative_path( $archive_file ),
			self::sanitize_relative_path( $path )
		);
	}

	/**
	 * Sanitizes a relative storage path while preserving directory separators.
	 *
	 * @param string $path Relative path.
	 */
	private static function sanitize_relative_path( string $path ): string {
		$path     = str_replace( '\\', '/', $path );
		$segments = array_filter(
			explode( '/', $path ),
			static fn ( string $segment ): bool => '' !== $segment && '.' !== $segment && '..' !== $segment
		);
		$segments = array_map( [ self::class, 'sanitize_path_segment' ], $segments );
		$segments = array_filter(
			$segments,
			static fn ( string $segment ): bool => '' !== $segment
		);

		return implode( '/', $segments );
	}

	/**
	 * Sanitizes one storage path segment.
	 *
	 * @param string $segment Relative path segment.
	 */
	private static function sanitize_path_segment( string $segment ): string {
		$sanitized = sanitize_file_name( $segment );

		if ( '' === $sanitized || str_contains( $segment, '.' ) ) {
			return $sanitized;
		}

		$key = sanitize_key( $segment );

		if ( '' !== $key && 'unnamed-file.' . $key === $sanitized ) {
			return $key;
		}

		return $sanitized;
	}
}
