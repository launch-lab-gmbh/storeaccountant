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

namespace StoreAccountant\Customer\Export\Query;

use DateTimeImmutable;
use DateTimeZone;
use WC_Customer;
use WP_Error;
use WP_User_Query;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use function array_filter;
use function array_map;
use function array_values;
use function class_exists;
use function count;
use function is_array;
use function is_numeric;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries WooCommerce customers for configured export filters.
 */
final readonly class CustomerQuery {
	private const QUERY_PAGE_SIZE = 100;

	/**
	 * Initializes the customer query service.
	 *
	 * @param ExportFilterRegistry $filters Export filter registry.
	 */
	public function __construct(
		private ExportFilterRegistry $filters
	) {}

	/**
	 * Gets WooCommerce customers for the export.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, WC_Customer>|WP_Error
	 */
	public function get_customers( ExportPayload $payload ): array|WP_Error {
		$ids = $this->get_customer_ids( $payload );

		return is_wp_error( $ids ) ? $ids : $this->get_customers_by_ids( $ids );
	}

	/**
	 * Counts WooCommerce customers for the export.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return int|WP_Error
	 */
	public function count_customers( ExportPayload $payload ): int|WP_Error {
		$criteria = $this->get_criteria( $payload );

		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$count  = 0;
		$offset = 0;

		do {
			$ids = $this->get_customer_id_page( $criteria, $offset, self::QUERY_PAGE_SIZE );

			foreach ( $ids as $customer_id ) {
				if ( $this->customer_has_orders( $customer_id ) ) {
					++$count;
				}
			}

			$id_count = count( $ids );
			$offset  += self::QUERY_PAGE_SIZE;
		} while ( self::QUERY_PAGE_SIZE === $id_count );

		return $count;
	}

	/**
	 * Gets one deterministic WooCommerce customer batch.
	 *
	 * @param ExportPayload $payload Export payload.
	 * @param int           $offset  Zero-based item offset.
	 * @param int           $limit   Batch size.
	 *
	 * @return array<int, WC_Customer>|WP_Error
	 */
	public function get_customer_batch( ExportPayload $payload, int $offset, int $limit ): array|WP_Error {
		$criteria = $this->get_criteria( $payload );

		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$ids           = [];
		$page_offset   = 0;
		$seen_eligible = 0;

		do {
			$page_ids = $this->get_customer_id_page( $criteria, $page_offset, self::QUERY_PAGE_SIZE );

			foreach ( $page_ids as $customer_id ) {
				if ( ! $this->customer_has_orders( $customer_id ) ) {
					continue;
				}

				if ( $seen_eligible < $offset ) {
					++$seen_eligible;
					continue;
				}

				$ids[] = $customer_id;

				if ( count( $ids ) >= $limit ) {
					return $this->get_customers_by_ids( $ids );
				}
			}

			$page_id_count = count( $page_ids );
			$page_offset  += self::QUERY_PAGE_SIZE;
		} while ( self::QUERY_PAGE_SIZE === $page_id_count );

		return $this->get_customers_by_ids( $ids );
	}

	/**
	 * Gets matching WooCommerce customer IDs.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return array<int, int>|WP_Error
	 */
	public function get_customer_ids( ExportPayload $payload ): array|WP_Error {
		if ( ! class_exists( WC_Customer::class ) || ! class_exists( WP_User_Query::class ) ) {
			return [];
		}

		$criteria = $this->get_criteria( $payload );

		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$query = new WP_User_Query( $this->get_query_args( $criteria ) );
		$users = $query->get_results();

		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $user ): int => is_numeric( $user ) || isset( $user->ID ) ? (int) ( is_numeric( $user ) ? $user : $user->ID ) : 0,
					is_array( $users ) ? $users : []
				),
				fn ( int $customer_id ): bool => $customer_id > 0 && $this->customer_has_orders( $customer_id )
			)
		);
	}

	/**
	 * Gets WooCommerce customers by IDs.
	 *
	 * @param array<int, int|string> $ids Customer IDs.
	 *
	 * @return array<int, WC_Customer>
	 */
	public function get_customers_by_ids( array $ids ): array {
		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $customer_id ): ?WC_Customer => is_numeric( $customer_id ) ? new WC_Customer( (int) $customer_id ) : null,
					$ids
				),
				static fn ( mixed $customer ): bool => $customer instanceof WC_Customer && $customer->get_id() > 0 && $customer->get_order_count() > 0
			)
		);
	}

	/**
	 * Gets user query arguments from the criteria.
	 *
	 * @param CustomerQueryCriteria $criteria Query criteria.
	 *
	 * @return array<string, mixed>
	 */
	private function get_query_args( CustomerQueryCriteria $criteria, int $number = -1, int $offset = 0 ): array {
		$args = [
			'fields' => [ 'ID' ],
			'number' => $number,
			'offset' => max( 0, $offset ),
		];

		if ( null !== $criteria->period ) {
			$args = $this->add_period_args( $args, $criteria );
		}

		if ( $criteria->include_all_countries && $criteria->include_unassigned_country ) {
			return $args;
		}

		if ( $criteria->include_all_countries || [] !== $criteria->countries || $criteria->include_unassigned_country ) {
			$country_query = [
				'relation' => 'OR',
			];

			if ( $criteria->include_all_countries ) {
				$country_query[] = [
					'relation' => 'AND',
					[
						'key'     => $criteria->country_field,
						'compare' => 'EXISTS',
					],
					[
						'key'     => $criteria->country_field,
						'value'   => '',
						'compare' => '!=',
					],
				];
			}

			if ( [] !== $criteria->countries ) {
				$country_query[] = [
					'key'     => $criteria->country_field,
					'value'   => $criteria->countries,
					'compare' => 'IN',
				];
			}

			if ( $criteria->include_unassigned_country ) {
				$country_query[] = [
					'key'     => $criteria->country_field,
					'compare' => 'NOT EXISTS',
				];
				$country_query[] = [
					'key'     => $criteria->country_field,
					'value'   => '',
					'compare' => '=',
				];
			}

			$args['meta_query'][] = $country_query;
		}

		return $args;
	}

	/**
	 * Builds customer query criteria from configured filters.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return CustomerQueryCriteria|WP_Error
	 */
	private function get_criteria( ExportPayload $payload ): CustomerQueryCriteria|WP_Error {
		if ( ! class_exists( WC_Customer::class ) || ! class_exists( WP_User_Query::class ) ) {
			return new WP_Error(
				'storeaccountant_woocommerce_customers_unavailable',
				__( 'WooCommerce customers are unavailable.', 'storeaccountant' )
			);
		}

		$criteria = new CustomerQueryCriteria();

		foreach ( $payload->filters as $selection ) {
			$filter = $this->filters->get( $selection->filter_id );

			if ( null === $filter || ! $filter->supports( CustomerExportAdapter::ADAPTER_ID ) ) {
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
	 * Gets one raw customer ID page.
	 *
	 * @param CustomerQueryCriteria $criteria Query criteria.
	 * @param int                   $offset   Query offset.
	 * @param int                   $limit    Query limit.
	 *
	 * @return array<int, int>
	 */
	private function get_customer_id_page( CustomerQueryCriteria $criteria, int $offset, int $limit ): array {
		$query = new WP_User_Query( $this->get_query_args( $criteria, $limit, $offset ) );
		$users = $query->get_results();

		return array_values(
			array_filter(
				array_map(
					static fn ( mixed $user ): int => is_numeric( $user ) || isset( $user->ID ) ? (int) ( is_numeric( $user ) ? $user : $user->ID ) : 0,
					is_array( $users ) ? $users : []
				),
				static fn ( int $customer_id ): bool => $customer_id > 0
			)
		);
	}

	/**
	 * Checks whether a customer has at least one order.
	 *
	 * @param int $customer_id Customer user ID.
	 */
	private function customer_has_orders( int $customer_id ): bool {
		return ( new WC_Customer( $customer_id ) )->get_order_count() > 0;
	}

	/**
	 * Adds date period query arguments.
	 *
	 * @param array<string, mixed>  $args     Query arguments.
	 * @param CustomerQueryCriteria $criteria Query criteria.
	 *
	 * @return array<string, mixed>
	 */
	private function add_period_args( array $args, CustomerQueryCriteria $criteria ): array {
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $criteria->period?->start_at, new DateTimeZone( 'UTC' ) );
		$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $criteria->period?->end_at, new DateTimeZone( 'UTC' ) );

		if ( false === $start || false === $end ) {
			return $args;
		}

		if ( CustomerQueryCriteria::DATE_FIELD_MODIFIED === $criteria->date_field ) {
			$args['meta_query'][] = [
				'key'     => 'last_update',
				'value'   => [ $start->getTimestamp(), $end->getTimestamp() ],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			];

			return $args;
		}

		$args['date_query'][] = [
			'column'    => 'user_registered',
			'after'     => $start->format( 'Y-m-d H:i:s' ),
			'before'    => $end->format( 'Y-m-d H:i:s' ),
			'inclusive' => true,
		];

		return $args;
	}
}
