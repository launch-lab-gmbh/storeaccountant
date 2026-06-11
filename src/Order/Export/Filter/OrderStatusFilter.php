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

namespace StoreAccountant\Order\Export\Filter;

use WC_Order_Query;
use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\OrderStatusProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters WooCommerce orders by status.
 */
final readonly class OrderStatusFilter implements ExportFilterInterface, HookRegistrarInterface {
	public const FILTER_ID = 'order_status';

	/**
	 * Initializes the order status filter.
	 *
	 * @param OrderStatusProvider $order_statuses Order status provider.
	 */
	public function __construct(
		private OrderStatusProvider $order_statuses
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter',
			function ( array $filters ): array {
				$filters[ self::FILTER_ID ] = $this;

				return $filters;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error {
		if ( ! $query instanceof WC_Order_Query ) {
			return new WP_Error( 'storeaccountant_invalid_order_query', __( 'The order status filter requires a WooCommerce order query.', 'storeaccountant' ) );
		}

		$statuses = $this->order_statuses->sanitize_statuses( $selection->settings['statuses'] ?? [] );

		if ( [] === $statuses ) {
			return new WP_Error( 'storeaccountant_invalid_order_statuses', __( 'Select at least one order status.', 'storeaccountant' ) );
		}

		$query->set( 'status', $statuses );

		return true;
	}
}
