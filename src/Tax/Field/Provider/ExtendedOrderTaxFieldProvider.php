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
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use function array_filter;
use function array_keys;
use function is_array;
use function is_int;
use function number_format;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides tax fields per WooCommerce tax rate.
 */
final readonly class ExtendedOrderTaxFieldProvider implements OrderTaxFieldProviderInterface, FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID              = 'extended';
	private const FIELD_PROVIDER_PRIORITY = HookRegistrarInterface::DEFAULT_PRIORITY + 10;

	/**
	 * Initializes the tax field provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param OrderTaxRateResolver $tax_rates Tax rate resolver.
	 */
	public function __construct(
		private OrderTaxRateResolver $tax_rates
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
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
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type
			&& self::PROVIDER_ID === (string) $context->get( 'tax_field_provider_id', self::PROVIDER_ID );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_label(): string {
		return __( 'Extended tax fields', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_fields( ExportContext $context ): array {
		$fields = [];

		foreach ( array_keys( $this->get_tax_rates_from_context( $context ) ) as $rate_key ) {
			foreach ( [ 'items', 'shipping', 'total' ] as $suffix ) {
				$id            = 'tax_' . $rate_key . '_' . $suffix;
				$fields[ $id ] = new Field( $id, $id, new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) );
			}
		}

		return $fields;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_values( WC_Order $order, ExportContext $context ): array {
		$tax_rates  = $this->get_tax_rates_from_context( $context );
		$tax_totals = $this->get_order_tax_totals( $order, $tax_rates );
		$values     = [];

		foreach ( array_keys( $tax_rates ) as $rate_key ) {
			$values[ 'tax_' . $rate_key . '_items' ]    = new FieldValue( 'tax_' . $rate_key . '_items', $this->format_amount( $tax_totals[ $rate_key ]['items'] ?? 0.0 ) );
			$values[ 'tax_' . $rate_key . '_shipping' ] = new FieldValue( 'tax_' . $rate_key . '_shipping', $this->format_amount( $tax_totals[ $rate_key ]['shipping'] ?? 0.0 ) );
			$values[ 'tax_' . $rate_key . '_total' ]    = new FieldValue( 'tax_' . $rate_key . '_total', $this->format_amount( $tax_totals[ $rate_key ]['total'] ?? 0.0 ) );
		}

		return $values;
	}

	/**
	 * Gets tax rates from the context.
	 *
	 * @param ExportContext $context Export context.
	 *
	 * @return array<string, int>
	 */
	private function get_tax_rates_from_context( ExportContext $context ): array {
		$tax_rates = $context->get( 'tax_rates', [] );

		return is_array( $tax_rates ) ? array_filter( $tax_rates, is_int( ... ) ) : [];
	}

	/**
	 * Gets tax totals for a WooCommerce order grouped by rate.
	 *
	 * @param WC_Order           $order     WooCommerce order.
	 * @param array<string, int> $tax_rates Tax rate IDs keyed by tax rate key.
	 *
	 * @return array<string, array{items: float, shipping: float, total: float}>
	 */
	private function get_order_tax_totals( WC_Order $order, array $tax_rates ): array {
		$tax_totals = [];

		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( ! $tax_item instanceof WC_Order_Item_Tax ) {
				continue;
			}

			$rate_key = $this->tax_rates->get_tax_rate_key( $tax_item, $tax_rates );

			if ( '' === $rate_key ) {
				continue;
			}

			$item_tax     = (float) $tax_item->get_tax_total();
			$shipping_tax = (float) $tax_item->get_shipping_tax_total();

			$tax_totals[ $rate_key ] = [
				'items'    => ( $tax_totals[ $rate_key ]['items'] ?? 0.0 ) + $item_tax,
				'shipping' => ( $tax_totals[ $rate_key ]['shipping'] ?? 0.0 ) + $shipping_tax,
				'total'    => ( $tax_totals[ $rate_key ]['total'] ?? 0.0 ) + $item_tax + $shipping_tax,
			];
		}

		return $tax_totals;
	}

	/**
	 * Formats an amount for export output.
	 */
	private function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}
}
