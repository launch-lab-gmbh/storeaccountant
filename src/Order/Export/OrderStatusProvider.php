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

namespace StoreAccountant\Order\Export;

use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function function_exists;
use function is_array;
use function is_string;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce order statuses for export filtering.
 */
final readonly class OrderStatusProvider {
	/**
	 * Gets all WooCommerce order statuses.
	 *
	 * @return array<string, string>
	 */
	public function get_statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return [];
		}

		$statuses = wc_get_order_statuses();

		if ( ! is_array( $statuses ) ) {
			return [];
		}

		return array_filter(
			$statuses,
			static fn ( mixed $label, mixed $status ): bool => is_string( $status ) && '' !== $status && is_string( $label ) && '' !== $label,
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Gets the default selected order statuses.
	 *
	 * @return array<int, string>
	 */
	public function get_default_statuses(): array {
		return array_keys( $this->get_statuses() );
	}

	/**
	 * Sanitizes requested order statuses against the available WooCommerce statuses.
	 *
	 * @param mixed $value Submitted order status value.
	 *
	 * @return array<int, string>
	 */
	public function sanitize_statuses( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		$available = array_keys( $this->get_statuses() );
		$statuses  = array_map(
			static fn ( mixed $status ): string => is_string( $status ) ? sanitize_key( wp_unslash( $status ) ) : '',
			$value
		);
		$statuses  = array_values( array_unique( array_filter( $statuses ) ) );

		return array_values( array_intersect( $statuses, $available ) );
	}
}
