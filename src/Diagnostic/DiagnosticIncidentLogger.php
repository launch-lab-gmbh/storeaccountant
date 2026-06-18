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

use Throwable;
use WP_Error;
use function __;
use function array_map;
use function current_time;
use function is_array;
use function is_object;
use function is_scalar;
use function is_wp_error;
use function sprintf;
use function wp_generate_uuid4;
use function wp_json_encode;
use function wp_trigger_error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates redacted diagnostic incident files.
 */
final readonly class DiagnosticIncidentLogger {
	/**
	 * Initializes the logger.
	 *
	 * @param DiagnosticSettings           $settings   Diagnostic settings.
	 * @param DiagnosticIncidentRepository $repository Incident repository.
	 */
	public function __construct(
		private DiagnosticSettings $settings,
		private DiagnosticIncidentRepository $repository
	) {}

	/**
	 * Logs an error incident when diagnostic logging is enabled.
	 *
	 * @param string               $source    Incident source.
	 * @param string               $message   User-facing summary.
	 * @param array<string, mixed> $context   Safe diagnostic context.
	 * @param WP_Error|null        $wp_error  Optional WordPress error.
	 * @param Throwable|null       $throwable Optional throwable.
	 */
	public function error( string $source, string $message, array $context = [], ?WP_Error $wp_error = null, ?Throwable $throwable = null ): ?DiagnosticIncident {
		if ( ! $this->settings->is_enabled() ) {
			return null;
		}

		$support_id = wp_generate_uuid4();

		$payload = [
			'support_id' => $support_id,
			'created_at' => current_time( 'mysql', true ),
			'level'      => 'error',
			'source'     => $source,
			'message'    => $message,
			'context'    => $this->sanitize_value( $context ),
		];

		if ( null !== $wp_error ) {
			$error_code          = $wp_error->get_error_code();
			$payload['wp_error'] = [
				'code'    => $error_code,
				'message' => $wp_error->get_error_message( $error_code ),
				'data'    => $this->sanitize_value( $wp_error->get_error_data( $error_code ) ),
			];
		}

		if ( null !== $throwable ) {
			$payload['exception'] = [
				'class'   => $throwable::class,
				'message' => $throwable->getMessage(),
				'file'    => $throwable->getFile(),
				'line'    => $throwable->getLine(),
			];
		}

		$incident = $this->repository->store( $support_id, $payload );

		$this->log_to_wordpress_debug( $payload, is_wp_error( $incident ) ? $incident : null );

		return is_wp_error( $incident ) ? null : $incident;
	}

	/**
	 * Writes the redacted diagnostic payload to the WordPress debug log.
	 *
	 * @param array<string, mixed> $payload Incident payload.
	 * @param WP_Error|null        $storage_error Optional incident file storage error.
	 */
	private function log_to_wordpress_debug( array $payload, ?WP_Error $storage_error = null ): void {
		if ( null !== $storage_error ) {
			$error_code               = $storage_error->get_error_code();
			$payload['storage_error'] = [
				'code'    => $error_code,
				'message' => $storage_error->get_error_message( $error_code ),
				'data'    => $this->sanitize_value( $storage_error->get_error_data( $error_code ) ),
			];
		}

		$message = wp_json_encode( $payload );

		if ( false === $message ) {
			$message = sprintf(
				'{"support_id":"%1$s","source":"%2$s","message":"%3$s"}',
				(string) ( $payload['support_id'] ?? '' ),
				(string) ( $payload['source'] ?? '' ),
				(string) ( $payload['message'] ?? '' )
			);
		}

		wp_trigger_error(
			__METHOD__,
			sprintf( 'StoreAccountant diagnostic incident: %s', $message ),
			E_USER_WARNING
		);
	}

	/**
	 * Sanitizes context data for diagnostic files.
	 */
	private function sanitize_value( mixed $value ): mixed {
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_map( fn ( mixed $item ): mixed => $this->sanitize_value( $item ), $value );
		}

		if ( is_object( $value ) ) {
			return $value::class;
		}

		return 'unserializable';
	}
}
