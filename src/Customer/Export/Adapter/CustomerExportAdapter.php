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

namespace StoreAccountant\Customer\Export\Adapter;

use WC_Customer;
use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Query\CustomerQuery;
use StoreAccountant\Export\Contract\BatchExportAdapterInterface;
use StoreAccountant\Export\Contract\SnapshotExportAdapterInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use function add_filter;
use function array_map;
use function is_array;
use function is_int;
use function is_wp_error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports normalized WooCommerce customer data.
 */
final readonly class CustomerExportAdapter implements BatchExportAdapterInterface, SnapshotExportAdapterInterface, HookRegistrarInterface {
	public const ADAPTER_ID = 'customers';

	/**
	 * Initializes the customer adapter.
	 *
	 * @param CustomerQuery $customer_query Customer query service.
	 */
	public function __construct(
		private CustomerQuery $customer_query
	) {}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_id(): string {
		return self::ADAPTER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_items( ExportPayload $payload ): iterable|WP_Error {
		return $this->customer_query->get_customers( $payload );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_items( ExportPayload $payload ): int|WP_Error {
		return $this->customer_query->count_customers( $payload );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_batch_items( ExportPayload $payload, int $offset, int $limit ): iterable|WP_Error {
		return $this->customer_query->get_customer_batch( $payload, $offset, $limit );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_item_ids( ExportPayload $payload ): array|WP_Error {
		$ids = $this->customer_query->get_customer_ids( $payload );

		return is_wp_error( $ids ) ? $ids : array_map( 'strval', $ids );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_items_by_ids( ExportPayload $payload, array $item_ids ): iterable|WP_Error {
		return $this->customer_query->get_customers_by_ids( $item_ids );
	}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_additional_fields( ExportPayload $payload, ExportContext $context ): FieldCollection {
		return new FieldCollection();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_additional_values( mixed $item, ExportPayload $payload, ExportContext $context ): array {
		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_record_id( mixed $item ): string {
		return $item instanceof WC_Customer ? (string) $item->get_id() : '';
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
