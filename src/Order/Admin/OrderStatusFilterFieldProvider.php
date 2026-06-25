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

namespace StoreAccountant\Order\Admin;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\Filter\OrderStatusFilter;
use function is_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the order status filter fields.
 */
final readonly class OrderStatusFilterFieldProvider implements ExportFilterFieldProviderInterface, HookRegistrarInterface {
	/**
	 * Initializes the field provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param OrderStatusField $order_status_field Order status field.
	 */
	public function __construct(
		private OrderStatusField $order_status_field
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter_field_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return OrderStatusFilter::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false ): void {
		$statuses = null !== $selection && is_array( $selection->settings['statuses'] ?? null ) ? $selection->settings['statuses'] : [];

		$this->order_status_field->render( $statuses, $read_only );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_selection_from_request( array $request ): ExportFilterSelection|WP_Error {
		$statuses = $this->order_status_field->get_statuses_from_request( $request );

		if ( [] === $statuses ) {
			return new WP_Error( 'storeaccountant_invalid_order_statuses', __( 'Select at least one order status.', 'storeaccountant' ) );
		}

		return new ExportFilterSelection(
			$this->get_id(),
			[
				'statuses' => $statuses,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_default_selection(): ExportFilterSelection {
		return new ExportFilterSelection(
			$this->get_id(),
			[
				'statuses' => $this->order_status_field->get_default_statuses(),
			]
		);
	}
}
