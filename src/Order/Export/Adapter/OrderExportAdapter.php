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

namespace StoreAccountant\Order\Export\Adapter;

use WC_Order;
use WP_Error;
use StoreAccountant\Export\Contract\BatchExportAdapterInterface;
use StoreAccountant\Export\Contract\SnapshotExportAdapterInterface;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use StoreAccountant\Order\Export\Query\OrderQuery;
use StoreAccountant\Contract\HookRegistrarInterface;
use function add_filter;
use function array_map;
use function get_post_meta;
use function is_array;
use function is_int;
use function is_wp_error;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports normalized WooCommerce order data.
 */
final readonly class OrderExportAdapter implements BatchExportAdapterInterface, SnapshotExportAdapterInterface, HookRegistrarInterface {
	public const ADAPTER_ID = 'orders';

	/**
	 * Initializes the order adapter.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param OrderTaxRateResolver $tax_rates   Tax rate resolver.
	 * @param OrderQuery           $order_query Order query service.
	 */
	public function __construct(
		private OrderTaxRateResolver $tax_rates,
		private OrderQuery $order_query
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_adapter',
			function ( array $adapters ): array {
				$adapters[ self::ADAPTER_ID ] = $this;

				return $adapters;
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
		return self::ADAPTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_items( ExportPayload $payload ): iterable|WP_Error {
		return $this->order_query->get_orders( $payload );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function count_items( ExportPayload $payload ): int|WP_Error {
		return $this->order_query->count_orders( $payload );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_batch_items( ExportPayload $payload, int $offset, int $limit ): iterable|WP_Error {
		return $this->order_query->get_order_batch( $payload, $offset, $limit );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_item_ids( ExportPayload $payload ): array|WP_Error {
		$ids = $this->order_query->get_order_ids( $payload );

		return is_wp_error( $ids ) ? $ids : array_map( 'strval', $ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_items_by_ids( ExportPayload $payload, array $item_ids ): iterable|WP_Error {
		return $this->order_query->get_orders_by_ids( $item_ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_context( ExportPayload $payload, iterable $items ): ExportContext {
		$orders = is_array( $items ) ? $items : [];

		return new ExportContext(
			self::ADAPTER_ID,
			$this->get_configuration_id( $payload ),
			$orders,
			[
				'export_id'             => $payload->export_id,
				'tax_rates'             => $this->tax_rates->get_tax_rates( $orders ),
				'tax_field_provider_id' => $this->get_tax_field_provider_id( $payload ),
			]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_additional_fields( ExportPayload $payload, ExportContext $context ): FieldCollection {
		return new FieldCollection();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_additional_values( mixed $item, ExportPayload $payload, ExportContext $context ): array {
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_record_id( mixed $item ): string {
		return $item instanceof WC_Order ? (string) $item->get_id() : '';
	}

	/**
	 * Gets the selected tax field provider ID from the related configuration.
	 *
	 * @param ExportPayload $payload Export payload.
	 */
	private function get_configuration_id( ExportPayload $payload ): int {
		$configuration_id = $payload->options['configuration_id'] ?? 0;

		if ( ! is_int( $configuration_id ) || $configuration_id <= 0 ) {
			return 0;
		}

		return $configuration_id;
	}

	/**
	 * Gets the selected tax field provider ID from the related configuration.
	 *
	 * @param ExportPayload $payload Export payload.
	 */
	private function get_tax_field_provider_id( ExportPayload $payload ): string {
		$configuration_id = $this->get_configuration_id( $payload );

		if ( $configuration_id > 0 ) {
			$provider_id = sanitize_key( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER, true ) );

			return '' !== $provider_id ? $provider_id : ExtendedOrderTaxFieldProvider::PROVIDER_ID;
		}

		$provider_id = sanitize_key( (string) get_post_meta( $payload->export_id, ExportPostType::META_ORDER_TAX_FIELD_PROVIDER, true ) );

		return '' !== $provider_id ? $provider_id : ExtendedOrderTaxFieldProvider::PROVIDER_ID;
	}
}
