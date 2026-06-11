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

namespace StoreAccountant\Tax\Field\Provider;

use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use function str_starts_with;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides values for selected order tax export fields.
 */
final readonly class OrderTaxFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_tax';

	/**
	 * Initializes the tax field value provider.
	 *
	 * @param OrderTaxFieldProviderRegistry $tax_field_providers Tax field provider registry.
	 */
	public function __construct(
		private OrderTaxFieldProviderRegistry $tax_field_providers
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_value_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( Field $field, ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type
			&& ( 'tax_items_total' === $field->id || 'tax_shipping_total' === $field->id || str_starts_with( $field->id, 'tax_' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order ) {
			return [];
		}

		$provider = $this->tax_field_providers->get_provider( (string) $context->get( 'tax_field_provider_id', ExtendedOrderTaxFieldProvider::PROVIDER_ID ) );

		if ( null === $provider ) {
			return [];
		}

		return $fields->filter_values( $provider->get_values( $item, $context ) );
	}
}
