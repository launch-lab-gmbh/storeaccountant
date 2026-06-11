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

namespace StoreAccountant\Invoice;

use Throwable;
use WC_Order;
use function is_array;
use function is_object;
use function is_scalar;
use function ltrim;
use function method_exists;
use function sanitize_file_name;
use function sanitize_key;
use function str_ends_with;
use function strtolower;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides reusable helpers for invoice plugin integrations.
 */
final readonly class InvoicePluginHelper {
	/**
	 * Gets the first scalar order meta value from a list of candidate meta keys.
	 *
	 * @param WC_Order          $order     WooCommerce order.
	 * @param array<int,string> $meta_keys Candidate meta keys.
	 */
	public function get_first_scalar_meta( WC_Order $order, array $meta_keys ): string {
		if ( ! method_exists( $order, 'get_meta' ) ) {
			return '';
		}

		foreach ( $meta_keys as $meta_key ) {
			$value = $order->get_meta( $meta_key, true );

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Gets the first scalar value from an array stored in order meta.
	 *
	 * @param WC_Order          $order     WooCommerce order.
	 * @param string            $meta_key  Array meta key.
	 * @param array<int,string> $item_keys Candidate array item keys.
	 */
	public function get_first_scalar_array_meta_value( WC_Order $order, string $meta_key, array $item_keys ): string {
		if ( ! method_exists( $order, 'get_meta' ) ) {
			return '';
		}

		$data = $order->get_meta( $meta_key, true );

		if ( ! is_array( $data ) ) {
			return '';
		}

		foreach ( $item_keys as $item_key ) {
			$value = $data[ $item_key ] ?? '';

			if ( is_scalar( $value ) && '' !== (string) $value ) {
				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Formats a plugin-provided invoice number value.
	 *
	 * @param mixed $number Invoice number value.
	 */
	public function format_invoice_number_value( mixed $number ): string {
		if ( is_object( $number ) && method_exists( $number, 'get_formatted' ) ) {
			$formatted_number = $number->get_formatted();

			return is_scalar( $formatted_number ) ? (string) $formatted_number : '';
		}

		if ( is_scalar( $number ) ) {
			return (string) $number;
		}

		if ( is_object( $number ) && method_exists( $number, '__toString' ) ) {
			return (string) $number;
		}

		return '';
	}

	/**
	 * Formats a plugin-provided invoice date value.
	 *
	 * @param mixed  $date   Invoice date value.
	 * @param string $format Output date format.
	 */
	public function format_invoice_date_value( mixed $date, string $format = 'Y-m-d H:i:s' ): string {
		if ( is_object( $date ) && method_exists( $date, 'date' ) ) {
			try {
				$formatted = $date->date( $format );
			} catch ( Throwable ) {
				return '';
			}

			return is_scalar( $formatted ) ? (string) $formatted : '';
		}

		if ( is_object( $date ) && method_exists( $date, 'format' ) ) {
			try {
				$formatted = $date->format( $format );
			} catch ( Throwable ) {
				return '';
			}

			return is_scalar( $formatted ) ? (string) $formatted : '';
		}

		if ( is_scalar( $date ) ) {
			return (string) $date;
		}

		return '';
	}

	/**
	 * Sanitizes a file name and makes sure it has the expected extension.
	 *
	 * @param string $file_name File name.
	 * @param string $extension File extension without leading dot.
	 * @param string $fallback  Fallback base name.
	 */
	public function ensure_file_extension( string $file_name, string $extension, string $fallback = 'invoice' ): string {
		$file_name = sanitize_file_name( $file_name );
		$file_name = '' === $file_name ? $fallback : $file_name;
		$extension = sanitize_key( ltrim( $extension, '.' ) );

		if ( '' === $extension ) {
			return $file_name;
		}

		$extension = '.' . $extension;

		if ( ! str_ends_with( strtolower( $file_name ), $extension ) ) {
			$file_name .= $extension;
		}

		return $file_name;
	}
}
