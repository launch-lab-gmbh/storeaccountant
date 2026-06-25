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

namespace StoreAccountant\Product\Export\Adapter;

use WC_Product;
use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\BatchExportAdapterInterface;
use StoreAccountant\Export\Contract\SnapshotExportAdapterInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Product\Export\Query\ProductQuery;
use function add_filter;
use function array_map;
use function is_array;
use function is_int;
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports normalized WooCommerce product data.
 */
final readonly class ProductExportAdapter implements BatchExportAdapterInterface, SnapshotExportAdapterInterface, HookRegistrarInterface {
	public const ADAPTER_ID = 'products';

	/**
	 * Initializes the product adapter.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ProductQuery $product_query Product query service.
	 */
	public function __construct(
		private ProductQuery $product_query
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
		return $this->product_query->get_products( $payload );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function count_items( ExportPayload $payload ): int|WP_Error {
		return $this->product_query->count_products( $payload );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_batch_items( ExportPayload $payload, int $offset, int $limit ): iterable|WP_Error {
		return $this->product_query->get_product_batch( $payload, $offset, $limit );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_item_ids( ExportPayload $payload ): array|WP_Error {
		$ids = $this->product_query->get_product_ids( $payload );

		return is_wp_error( $ids ) ? $ids : array_map( 'strval', $ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_items_by_ids( ExportPayload $payload, array $item_ids ): iterable|WP_Error {
		return $this->product_query->get_products_by_ids( $item_ids );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_context( ExportPayload $payload, iterable $items ): ExportContext {
		return new ExportContext(
			self::ADAPTER_ID,
			$this->get_configuration_id( $payload ),
			is_array( $items ) ? $items : []
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
		return $item instanceof WC_Product ? (string) $item->get_id() : '';
	}

	/**
	 * Gets the related configuration ID from the payload.
	 *
	 * @param ExportPayload $payload Export payload.
	 */
	private function get_configuration_id( ExportPayload $payload ): int {
		$configuration_id = $payload->options['configuration_id'] ?? 0;

		return is_int( $configuration_id ) && $configuration_id > 0 ? $configuration_id : 0;
	}
}
