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
use WC_Order_Item_Tax;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use function number_format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides aggregated order tax fields.
 */
final readonly class SimpleOrderTaxFieldProvider implements OrderTaxFieldProviderInterface, FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID              = 'simple';
	private const FIELD_PROVIDER_PRIORITY = HookRegistrarInterface::DEFAULT_PRIORITY + 10;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_order_tax_field_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);

		add_filter(
			'storeaccountant_export_field_provider',
			function ( array $providers ): array {
				$providers[ 'order_tax_' . self::PROVIDER_ID ] = $this;

				return $providers;
			},
			self::FIELD_PROVIDER_PRIORITY
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
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type
			&& self::PROVIDER_ID === (string) $context->get( 'tax_field_provider_id', ExtendedOrderTaxFieldProvider::PROVIDER_ID );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Simple tax fields', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_fields( ExportContext $context ): array {
		return [
			'tax_items_total'    => new Field( 'tax_items_total', 'tax_items_total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
			'tax_shipping_total' => new Field( 'tax_shipping_total', 'tax_shipping_total', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( WC_Order $order, ExportContext $context ): array {
		$item_tax     = 0.0;
		$shipping_tax = 0.0;

		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( ! $tax_item instanceof WC_Order_Item_Tax ) {
				continue;
			}

			$item_tax     += (float) $tax_item->get_tax_total();
			$shipping_tax += (float) $tax_item->get_shipping_tax_total();
		}

		return [
			'tax_items_total'    => new FieldValue( 'tax_items_total', $this->format_amount( $item_tax ) ),
			'tax_shipping_total' => new FieldValue( 'tax_shipping_total', $this->format_amount( $shipping_tax ) ),
		];
	}

	/**
	 * Formats an amount for export output.
	 */
	private function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}
}
