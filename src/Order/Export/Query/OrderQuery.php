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

namespace StoreAccountant\Order\Export\Query;

use WC_Order_Query;
use WC_Order;
use WP_Error;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use function array_values;
use function array_filter;
use function array_map;
use function function_exists;
use function is_object;
use function is_numeric;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries WooCommerce orders for configured export filters.
 */
final readonly class OrderQuery {
	/**
	 * Initializes the order query service.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportFilterRegistry $filters Export filter registry.
	 */
	public function __construct(
		private ExportFilterRegistry $filters
	) {}

	/**
	 * Gets WooCommerce orders for the export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, WC_Order>|WP_Error
	 */
	public function get_orders( ExportPayload $payload ): array|WP_Error {
		$ids = $this->get_order_ids( $payload );

		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		return $this->get_orders_by_ids( $ids );
	}

	/**
	 * Counts WooCommerce orders for the export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return int|WP_Error
	 */
	public function count_orders( ExportPayload $payload ): int|WP_Error {
		$query = $this->get_order_query( $payload, 1, 0, true );

		if ( is_wp_error( $query ) ) {
			return $query;
		}

		$result = $query->get_orders();
		$total  = is_object( $result ) && isset( $result->total ) ? $result->total : 0;

		return is_numeric( $total ) ? (int) $total : 0;
	}

	/**
	 * Gets one deterministic WooCommerce order batch.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 * @param int           $offset  Zero-based item offset.
	 * @param int           $limit   Batch size.
	 *
	 * @return array<int, WC_Order>|WP_Error
	 */
	public function get_order_batch( ExportPayload $payload, int $offset, int $limit ): array|WP_Error {
		$ids = $this->get_order_ids( $payload, $limit, $offset );

		if ( is_wp_error( $ids ) ) {
			return $ids;
		}

		return $this->get_orders_by_ids( $ids );
	}

	/**
	 * Gets matching WooCommerce order IDs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, int>|WP_Error
	 */
	public function get_order_ids( ExportPayload $payload, int $limit = -1, int $offset = 0 ): array|WP_Error {
		$query = $this->get_order_query( $payload, $limit, $offset, false );

		if ( is_wp_error( $query ) ) {
			return $query;
		}

		return array_values(
			array_map(
				static fn ( mixed $order_id ): int => (int) $order_id,
				array_filter(
					$query->get_orders(),
					static fn ( mixed $order_id ): bool => is_numeric( $order_id ) && (int) $order_id > 0
				)
			)
		);
	}

	/**
	 * Builds a WooCommerce order query for the export filters.
	 *
	 * @param ExportPayload $payload  Export payload.
	 * @param int           $limit    Query limit.
	 * @param int           $offset   Query offset.
	 * @param bool          $paginate Whether WooCommerce should return totals.
	 *
	 * @return WC_Order_Query|WP_Error
	 */
	private function get_order_query( ExportPayload $payload, int $limit, int $offset, bool $paginate ): WC_Order_Query|WP_Error {
		if ( ! function_exists( 'wc_get_orders' ) || ! class_exists( WC_Order_Query::class ) ) {
			return new WP_Error(
				'storeaccountant_woocommerce_orders_unavailable',
				__( 'WooCommerce orders are unavailable.', 'storeaccountant' )
			);
		}

		$query = new WC_Order_Query(
			[
				'limit'    => $limit,
				'offset'   => max( 0, $offset ),
				'paginate' => $paginate,
				'return'   => 'ids',
				'type'     => 'shop_order',
				'orderby'  => 'ID',
				'order'    => 'ASC',
			]
		);

		foreach ( $payload->filters as $selection ) {
			$filter = $this->filters->get( $selection->filter_id );

			if ( null === $filter || ! $filter->supports( OrderExportAdapter::ADAPTER_ID ) ) {
				continue;
			}

			$result = $filter->apply( $query, $selection, $payload );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $query;
	}

	/**
	 * Gets WooCommerce orders by IDs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, int|string> $ids Order IDs.
	 *
	 * @return array<int, WC_Order>
	 */
	public function get_orders_by_ids( array $ids ): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $order_id ): mixed => function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order_id ) : null,
					$ids
				),
				static fn ( mixed $order ): bool => $order instanceof WC_Order
			)
		);
	}
}
