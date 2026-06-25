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

namespace StoreAccountant\Export\Download;

use StoreAccountant\Storage\StorageFile;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Storage adapters return PHP streams for browser downloads.
use function fclose;
use function fpassthru;
use function fstat;
use function header;
use function is_array;
use function is_resource;
use function nocache_headers;
use function ob_end_clean;
use function ob_get_level;
use function preg_match;
use function rawurlencode;
use function sanitize_file_name;
use function str_replace;
use function esc_html__;
use function wp_die;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams storage files with safe download headers.
 */
final readonly class StorageFileStreamer {
	/**
	 * Streams a storage file to the browser.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function stream( StorageFile $file ): void {
		if ( ! is_resource( $file->stream ) ) {
			wp_die(
				esc_html__( 'The requested export file is unavailable.', 'storeaccountant' ),
				esc_html__( 'Export Unavailable', 'storeaccountant' ),
				[
					'response' => 404,
				]
			);
		}

		$file_name = sanitize_file_name( $file->file_name );

		if ( '' === $file_name ) {
			$file_name = 'storeaccountant-export';
		}

		$mime_type = $this->sanitize_mime_type( $file->mime_type );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime_type );
		$stream_stat = fstat( $file->stream );

		if ( is_array( $stream_stat ) && isset( $stream_stat['size'] ) && $stream_stat['size'] >= 0 ) {
			header( 'Content-Length: ' . (string) $stream_stat['size'] );
		}

		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $file_name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $file_name ) );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'X-Content-Type-Options: nosniff' );

		fpassthru( $file->stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Storage adapters return PHP streams for browser downloads.
		fclose( $file->stream );
		exit;
	}

	/**
	 * Sanitizes a MIME type for download headers.
	 */
	private function sanitize_mime_type( string $mime_type ): string {
		if ( preg_match( '/^[a-z0-9][a-z0-9!#$&^_.+-]*\/[a-z0-9][a-z0-9!#$&^_.+-]*$/i', $mime_type ) ) {
			return $mime_type;
		}

		return 'application/octet-stream';
	}
}
