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

namespace StoreAccountant\Order\Tax;

use WC_Order;
use WC_Order_Item_Tax;
use WC_Tax;
use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function function_exists;
use function implode;
use function is_object;
use function is_string;
use function ksort;
use function method_exists;
use function number_format;
use function preg_replace;
use function rtrim;
use function str_replace;
use function strtolower;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves WooCommerce tax rates used by order exports.
 */
final readonly class OrderTaxRateResolver {
	/**
	 * Gets all configured WooCommerce tax rates plus tax rates discovered from orders.
	 *
	 * @param array<int, WC_Order> $orders WooCommerce orders.
	 *
	 * @return array<string, int>
	 */
	public function get_tax_rates( array $orders = [] ): array {
		$tax_rates = $this->get_configured_tax_rates();

		foreach ( $orders as $order ) {
			foreach ( $order->get_items( 'tax' ) as $tax_item ) {
				if ( ! $tax_item instanceof WC_Order_Item_Tax ) {
					continue;
				}

				$rate_key = $this->get_tax_rate_key( $tax_item, $tax_rates );

				if ( '' === $rate_key ) {
					continue;
				}

				$tax_rates[ $rate_key ] = $this->get_tax_rate_id( $tax_item );
			}
		}

		ksort( $tax_rates, SORT_NATURAL );

		return $tax_rates;
	}

	/**
	 * Gets a stable tax rate key.
	 *
	 * @param object             $tax                  Tax total or order tax item.
	 * @param array<string, int> $configured_tax_rates Tax rate IDs keyed by tax rate key.
	 */
	public function get_tax_rate_key( object $tax, array $configured_tax_rates ): string {
		$rate_id = $this->get_tax_rate_id( $tax );

		foreach ( $configured_tax_rates as $rate_key => $configured_rate_id ) {
			if ( $rate_id > 0 && $rate_id === $configured_rate_id ) {
				return $rate_key;
			}
		}

		$percent = method_exists( $tax, 'get_rate_percent' ) ? (float) $tax->get_rate_percent() : 0.0;

		if ( 0.0 === $percent && $rate_id > 0 && class_exists( WC_Tax::class ) ) {
			$percent = (float) WC_Tax::get_rate_percent_value( $rate_id );
		}

		if ( 0.0 === $percent && isset( $tax->percent ) ) {
			$percent = (float) $tax->percent;
		}

		if ( 0.0 === $percent ) {
			return '';
		}

		return $this->build_tax_rate_key( $percent, 'tax', '' );
	}

	/**
	 * Gets configured WooCommerce tax rates.
	 *
	 * @return array<string, int>
	 */
	private function get_configured_tax_rates(): array {
		if ( ! class_exists( WC_Tax::class ) ) {
			return [];
		}

		$tax_rates   = [];
		$tax_classes = [ '' ];

		if ( method_exists( WC_Tax::class, 'get_tax_classes' ) ) {
			$tax_classes = array_merge( $tax_classes, WC_Tax::get_tax_classes() );
		}

		if ( ! method_exists( WC_Tax::class, 'get_rates_for_tax_class' ) ) {
			return [];
		}

		foreach ( array_unique( array_filter( $tax_classes, 'is_string' ) ) as $tax_class ) {
			foreach ( WC_Tax::get_rates_for_tax_class( $tax_class ) as $rate ) {
				if ( ! is_object( $rate ) ) {
					continue;
				}

				$rate_id = isset( $rate->tax_rate_id ) ? (int) $rate->tax_rate_id : 0;
				$key     = $this->get_configured_tax_rate_key( $rate );

				if ( $rate_id <= 0 || '' === $key ) {
					continue;
				}

				$tax_rates[ $key ] = $rate_id;
			}
		}

		ksort( $tax_rates, SORT_NATURAL );

		return $tax_rates;
	}

	/**
	 * Gets the configured tax rate key for a WooCommerce tax rate row.
	 *
	 * @param object $rate WooCommerce tax rate row.
	 */
	private function get_configured_tax_rate_key( object $rate ): string {
		$percent = isset( $rate->tax_rate ) ? (float) $rate->tax_rate : 0.0;
		$name    = isset( $rate->tax_rate_name ) ? (string) $rate->tax_rate_name : 'tax';
		$country = isset( $rate->tax_rate_country ) ? (string) $rate->tax_rate_country : '';

		return $this->build_tax_rate_key( $percent, $name, $country );
	}

	/**
	 * Gets a tax rate ID from a tax object.
	 *
	 * @param object $tax Tax total or order tax item.
	 */
	private function get_tax_rate_id( object $tax ): int {
		if ( method_exists( $tax, 'get_rate_id' ) ) {
			return (int) $tax->get_rate_id();
		}

		if ( isset( $tax->rate_id ) ) {
			return (int) $tax->rate_id;
		}

		return 0;
	}

	/**
	 * Builds a tax rate key from percent, name, and country.
	 */
	private function build_tax_rate_key( float $percent, string $name, string $country ): string {
		if ( 0.0 === $percent ) {
			return '';
		}

		$formatted_percent = rtrim( rtrim( number_format( $percent, 4, '.', '' ), '0' ), '.' );
		$parts             = [
			str_replace( '.', '_', $formatted_percent ),
			$this->slugify_tax_part( $name ),
			$this->slugify_tax_part( $country ),
		];
		$parts             = array_values( array_filter( $parts, static fn ( string $part ): bool => '' !== $part ) );

		return implode( '_', $parts );
	}

	/**
	 * Slugifies one part of a tax rate key.
	 */
	private function slugify_tax_part( string $value ): string {
		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		}

		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );

		return is_string( $value ) ? trim( $value, '_' ) : '';
	}
}
