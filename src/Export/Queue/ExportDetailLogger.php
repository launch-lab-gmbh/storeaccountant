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

namespace StoreAccountant\Export\Queue;

use Throwable;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Export\ExportPostType;
use function array_map;
use function current_time;
use function get_post_meta;
use function ini_get;
use function is_array;
use function is_scalar;
use function is_string;
use function is_wp_error;
use function memory_get_peak_usage;
use function memory_get_usage;
use function preg_replace;
use function sprintf;
use function trailingslashit;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes per-export diagnostic detail logs without touching the WordPress debug log.
 */
final readonly class ExportDetailLogger {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private DiagnosticSettings $settings,
		private DiagnosticIncidentRepository $repository,
		private DiagnosticLogConfiguration $configuration
	) {}

	/**
	 * Writes one per-export diagnostic event when diagnostic logging is enabled.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int                  $export_id Export post ID.
	 * @param string               $level     Log level.
	 * @param string               $event     Event name.
	 * @param array<string, mixed> $context   Diagnostic context.
	 * @param Throwable|null       $exception Optional exception.
	 */
	public function log( int $export_id, string $level, string $event, array $context = [], ?Throwable $exception = null ): void {
		if ( ! $this->settings->is_enabled() ) {
			return;
		}

		$file_name = $this->get_log_file_name( $export_id );

		if ( '' === $file_name ) {
			return;
		}

		$ensured = $this->repository->ensure();

		if ( is_wp_error( $ensured ) ) {
			return;
		}

		$root_path = $this->configuration->get_root_path();

		if ( is_wp_error( $root_path ) ) {
			return;
		}

		$entry = [
			'time'    => current_time( 'mysql', true ),
			'level'   => $level,
			'event'   => $event,
			'export'  => [
				'id'             => $export_id,
				'download_token' => (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_TOKEN, true ),
			],
			'memory'  => [
				'usage_bytes' => memory_get_usage( true ),
				'peak_bytes'  => memory_get_peak_usage( true ),
				'limit'       => (string) ini_get( 'memory_limit' ),
			],
			'context' => $this->sanitize_value( $context ),
		];

		if ( null !== $exception ) {
			$entry['exception'] = [
				'class'   => $exception::class,
				'message' => $exception->getMessage(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
			];
		}

		$line = wp_json_encode( $entry, JSON_UNESCAPED_SLASHES );

		if ( false === $line ) {
			return;
		}

		$path     = trailingslashit( $root_path ) . $file_name;
		$previous = WordPressFilesystem::get_contents( $path );
		$contents = ( is_string( $previous ) ? $previous : '' ) . $line . "\n";

		WordPressFilesystem::put_contents( $path, $contents );
	}

	/**
	 * Builds the export-specific detail log file name.
	 *
	 * @param int $export_id Export post ID.
	 */
	private function get_log_file_name( int $export_id ): string {
		$token = (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_TOKEN, true );

		if ( '' === $token ) {
			$token = 'post-' . $export_id;
		}

		$token = preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $token );

		if ( ! is_string( $token ) || '' === $token ) {
			return '';
		}

		return sprintf( 'export-%s.log', $token );
	}

	/**
	 * Keeps detail log context scalar and safe to encode.
	 */
	private function sanitize_value( mixed $value ): mixed {
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_map( fn ( mixed $item ): mixed => $this->sanitize_value( $item ), $value );
		}

		return 'unserializable';
	}
}
