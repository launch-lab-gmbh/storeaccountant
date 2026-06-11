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

use function absint;
use function filter_input;
use function filter_input_array;
use function is_array;
use function is_scalar;
use function sanitize_key;
use function sanitize_text_field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads scalar request values without touching WordPress superglobals directly.
 */
final readonly class Request {
	/**
	 * Gets a sanitized key value from the query string.
	 *
	 * @param string $name    Request field name.
	 * @param string $fallback Fallback value.
	 */
	public static function get_key( string $name, string $fallback = '' ): string {
		$value = filter_input( INPUT_GET, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_key_value' ] ] );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Gets a positive integer value from the query string.
	 *
	 * @param string $name Request field name.
	 */
	public static function get_int( string $name ): int {
		$value = filter_input( INPUT_GET, $name, FILTER_SANITIZE_NUMBER_INT );

		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Checks whether a query string flag exists.
	 *
	 * @param string $name Request field name.
	 */
	public static function has_get( string $name ): bool {
		return null !== filter_input( INPUT_GET, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_text_value' ] ] );
	}

	/**
	 * Gets a sanitized text value from POST data.
	 *
	 * @param string $name    Request field name.
	 * @param string $fallback Fallback value.
	 */
	public static function post_text( string $name, string $fallback = '' ): string {
		$value = filter_input( INPUT_POST, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_text_value' ] ] );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Gets a sanitized key value from POST data.
	 *
	 * @param string $name     Request field name.
	 * @param string $fallback Fallback value.
	 */
	public static function post_key( string $name, string $fallback = '' ): string {
		$value = filter_input( INPUT_POST, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_key_value' ] ] );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Gets a positive integer value from POST data.
	 *
	 * @param string $name Request field name.
	 */
	public static function post_int( string $name ): int {
		$value = filter_input( INPUT_POST, $name, FILTER_SANITIZE_NUMBER_INT );

		return is_scalar( $value ) ? absint( $value ) : 0;
	}

	/**
	 * Gets sanitized POST data for request-specific validators.
	 *
	 * @return array<string, mixed>
	 */
	public static function post_data(): array {
		$value = filter_input_array(
			INPUT_POST,
			[
				'filter'  => FILTER_CALLBACK,
				'options' => [ self::class, 'sanitize_text_value' ],
			]
		);

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Gets a sanitized POST array value for request-specific validators.
	 *
	 * @param string $name Request field name.
	 *
	 * @return array<int|string, mixed>
	 */
	public static function post_array( string $name ): array {
		$value = filter_input(
			INPUT_POST,
			$name,
			FILTER_CALLBACK,
			[
				'flags'   => FILTER_REQUIRE_ARRAY,
				'options' => [ self::class, 'sanitize_text_value' ],
			]
		);

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Gets a sanitized key value from server request metadata.
	 *
	 * @param string $name     Server field name.
	 * @param string $fallback Fallback value.
	 */
	public static function server_key( string $name, string $fallback = '' ): string {
		$value = filter_input( INPUT_SERVER, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_key_value' ] ] );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Gets a sanitized text value from server request metadata.
	 *
	 * @param string $name     Server field name.
	 * @param string $fallback Fallback value.
	 */
	public static function server_text( string $name, string $fallback = '' ): string {
		$value = filter_input( INPUT_SERVER, $name, FILTER_CALLBACK, [ 'options' => [ self::class, 'sanitize_text_value' ] ] );

		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Sanitizes a scalar value as text for filter callbacks.
	 */
	public static function sanitize_text_value( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * Sanitizes a scalar value as a key for filter callbacks.
	 */
	public static function sanitize_key_value( mixed $value ): string {
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}
}
