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

namespace StoreAccountant\Product\Export\Query;

use DateTimeImmutable;
use DateTimeZone;
use WC_Product;
use WP_Error;
use WP_Query;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use function __;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function class_exists;
use function function_exists;
use function is_array;
use function is_numeric;
use function is_wp_error;
use function max;
use function wc_get_product;
use function wc_get_product_statuses;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries WooCommerce products for configured export filters.
 */
final readonly class ProductQuery {
	/**
	 * Initializes the product query service.
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
	 * Gets WooCommerce products for the export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, WC_Product>|WP_Error
	 */
	public function get_products( ExportPayload $payload ): array|WP_Error {
		$ids = $this->get_product_ids( $payload );

		return is_wp_error( $ids ) ? $ids : $this->get_products_by_ids( $ids );
	}

	/**
	 * Counts WooCommerce products for the export.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return int|WP_Error
	 */
	public function count_products( ExportPayload $payload ): int|WP_Error {
		$criteria = $this->get_criteria( $payload );

		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$query = new WP_Query( $this->get_query_args( $criteria, 1, 0, true ) );

		return (int) $query->found_posts;
	}

	/**
	 * Gets one deterministic WooCommerce product batch.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 * @param int           $offset  Zero-based item offset.
	 * @param int           $limit   Batch size.
	 *
	 * @return array<int, WC_Product>|WP_Error
	 */
	public function get_product_batch( ExportPayload $payload, int $offset, int $limit ): array|WP_Error {
		$ids = $this->get_product_ids( $payload, $limit, $offset );

		return is_wp_error( $ids ) ? $ids : $this->get_products_by_ids( $ids );
	}

	/**
	 * Gets matching WooCommerce product IDs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, int>|WP_Error
	 */
	public function get_product_ids( ExportPayload $payload, int $limit = -1, int $offset = 0 ): array|WP_Error {
		$criteria = $this->get_criteria( $payload );

		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$query = new WP_Query( $this->get_query_args( $criteria, $limit, $offset, false ) );

		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $product_id ): int => is_numeric( $product_id ) ? (int) $product_id : 0,
					is_array( $query->posts ) ? $query->posts : []
				),
				static fn ( int $product_id ): bool => $product_id > 0
			)
		);
	}

	/**
	 * Builds product query criteria from configured filters.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return ProductQueryCriteria|WP_Error
	 */
	private function get_criteria( ExportPayload $payload ): ProductQueryCriteria|WP_Error {
		if ( ! class_exists( WC_Product::class ) || ! class_exists( WP_Query::class ) || ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error(
				'storeaccountant_woocommerce_products_unavailable',
				__( 'WooCommerce products are unavailable.', 'storeaccountant' )
			);
		}

		$criteria = new ProductQueryCriteria();

		foreach ( $payload->filters as $selection ) {
			$filter = $this->filters->get( $selection->filter_id );

			if ( null === $filter || ! $filter->supports( ProductExportAdapter::ADAPTER_ID ) ) {
				continue;
			}

			$result = $filter->apply( $criteria, $selection, $payload );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $criteria;
	}

	/**
	 * Gets product query arguments from the criteria.
	 *
	 * @param ProductQueryCriteria $criteria Query criteria.
	 *
	 * @return array<string, mixed>
	 */
	private function get_query_args( ProductQueryCriteria $criteria, int $limit, int $offset, bool $paginate ): array {
		$args = [
			'fields'         => 'ids',
			'post_type'      => $criteria->export_variations ? [ 'product', 'product_variation' ] : [ 'product' ],
			'post_status'    => $this->get_product_statuses(),
			'posts_per_page' => $limit,
			'offset'         => max( 0, $offset ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => ! $paginate,
		];

		if ( null !== $criteria->period ) {
			$args['date_query'][] = $this->get_date_query( $criteria );
		}

		return $args;
	}

	/**
	 * Gets the product creation date query.
	 *
	 * @param ProductQueryCriteria $criteria Query criteria.
	 *
	 * @return array<string, mixed>
	 */
	private function get_date_query( ProductQueryCriteria $criteria ): array {
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $criteria->period?->start_at, new DateTimeZone( 'UTC' ) );
		$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $criteria->period?->end_at, new DateTimeZone( 'UTC' ) );

		if ( false === $start || false === $end ) {
			return [];
		}

		return [
			'column'    => 'post_date_gmt',
			'after'     => $start->format( 'Y-m-d H:i:s' ),
			'before'    => $end->format( 'Y-m-d H:i:s' ),
			'inclusive' => true,
		];
	}

	/**
	 * Gets WooCommerce product statuses that should be considered exportable.
	 *
	 * @return array<int, string>
	 */
	private function get_product_statuses(): array {
		if ( function_exists( 'wc_get_product_statuses' ) ) {
			return array_values( array_keys( wc_get_product_statuses() ) );
		}

		return [ 'publish', 'private', 'draft', 'pending' ];
	}

	/**
	 * Gets WooCommerce products by IDs.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, int|string> $ids Product IDs.
	 *
	 * @return array<int, WC_Product>
	 */
	public function get_products_by_ids( array $ids ): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $product_id ): mixed => function_exists( 'wc_get_product' ) ? wc_get_product( (int) $product_id ) : null,
					$ids
				),
				static fn ( mixed $product ): bool => $product instanceof WC_Product
			)
		);
	}
}
