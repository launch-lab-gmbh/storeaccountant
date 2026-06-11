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

namespace StoreAccountant\Order\Export\Field\Provider;

use WC_DateTime;
use WC_Order;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use function in_array;
use function method_exists;
use function number_format;
use function preg_match;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce order export field values.
 */
final readonly class OrderFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'orders';

	/**
	 * Field IDs resolved by this provider.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_FIELD_IDS = [
		'order_id',
		'order_number',
		'order_date',
		'order_status',
		'currency',
		'payment_method',
		'payment_method_title',
		'customer_id',
		'billing_first_name',
		'billing_last_name',
		'billing_company',
		'billing_street',
		'billing_house_number',
		'billing_address_1',
		'billing_address_2',
		'billing_postcode',
		'billing_city',
		'billing_state',
		'billing_country',
		'billing_email',
		'billing_phone',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_company',
		'shipping_street',
		'shipping_house_number',
		'shipping_address_1',
		'shipping_address_2',
		'shipping_postcode',
		'shipping_city',
		'shipping_state',
		'shipping_country',
		'shipping_phone',
		'order_subtotal',
		'discount_total',
		'shipping_total',
		'fee_total',
		'order_total',
	];

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
		return OrderExportAdapter::ADAPTER_ID === $context->export_type && $this->is_supported_order_field( $field );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( OrderExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Order ) {
			return [];
		}

		$order                  = $item;
		$billing_address_parts  = $this->split_street_and_house_number( $order->get_billing_address_1() );
		$shipping_address_parts = $this->split_street_and_house_number( $order->get_shipping_address_1() );
		$values                 = [
			'order_id'              => new FieldValue( 'order_id', $order->get_id() ),
			'order_number'          => new FieldValue( 'order_number', $order->get_order_number() ),
			'order_date'            => new FieldValue( 'order_date', $this->format_datetime( $order->get_date_created() ) ),
			'order_status'          => new FieldValue( 'order_status', $order->get_status() ),
			'currency'              => new FieldValue( 'currency', $order->get_currency() ),
			'payment_method'        => new FieldValue( 'payment_method', $order->get_payment_method() ),
			'payment_method_title'  => new FieldValue( 'payment_method_title', $order->get_payment_method_title() ),
			'customer_id'           => new FieldValue( 'customer_id', $order->get_customer_id() ),
			'billing_first_name'    => new FieldValue( 'billing_first_name', $order->get_billing_first_name() ),
			'billing_last_name'     => new FieldValue( 'billing_last_name', $order->get_billing_last_name() ),
			'billing_company'       => new FieldValue( 'billing_company', $order->get_billing_company() ),
			'billing_street'        => new FieldValue( 'billing_street', $billing_address_parts['street'] ),
			'billing_house_number'  => new FieldValue( 'billing_house_number', $billing_address_parts['house_number'] ),
			'billing_address_1'     => new FieldValue( 'billing_address_1', $order->get_billing_address_1() ),
			'billing_address_2'     => new FieldValue( 'billing_address_2', $order->get_billing_address_2() ),
			'billing_postcode'      => new FieldValue( 'billing_postcode', $order->get_billing_postcode() ),
			'billing_city'          => new FieldValue( 'billing_city', $order->get_billing_city() ),
			'billing_state'         => new FieldValue( 'billing_state', $order->get_billing_state() ),
			'billing_country'       => new FieldValue( 'billing_country', $order->get_billing_country() ),
			'billing_email'         => new FieldValue( 'billing_email', $order->get_billing_email() ),
			'billing_phone'         => new FieldValue( 'billing_phone', $order->get_billing_phone() ),
			'shipping_first_name'   => new FieldValue( 'shipping_first_name', $order->get_shipping_first_name() ),
			'shipping_last_name'    => new FieldValue( 'shipping_last_name', $order->get_shipping_last_name() ),
			'shipping_company'      => new FieldValue( 'shipping_company', $order->get_shipping_company() ),
			'shipping_street'       => new FieldValue( 'shipping_street', $shipping_address_parts['street'] ),
			'shipping_house_number' => new FieldValue( 'shipping_house_number', $shipping_address_parts['house_number'] ),
			'shipping_address_1'    => new FieldValue( 'shipping_address_1', $order->get_shipping_address_1() ),
			'shipping_address_2'    => new FieldValue( 'shipping_address_2', $order->get_shipping_address_2() ),
			'shipping_postcode'     => new FieldValue( 'shipping_postcode', $order->get_shipping_postcode() ),
			'shipping_city'         => new FieldValue( 'shipping_city', $order->get_shipping_city() ),
			'shipping_state'        => new FieldValue( 'shipping_state', $order->get_shipping_state() ),
			'shipping_country'      => new FieldValue( 'shipping_country', $order->get_shipping_country() ),
			'shipping_phone'        => new FieldValue( 'shipping_phone', $this->get_shipping_phone( $order ) ),
			'order_subtotal'        => new FieldValue( 'order_subtotal', $this->format_amount( (float) $order->get_subtotal() ) ),
			'discount_total'        => new FieldValue( 'discount_total', $this->format_amount( (float) $order->get_discount_total() ) ),
			'shipping_total'        => new FieldValue( 'shipping_total', $this->format_amount( (float) $order->get_shipping_total() ) ),
			'fee_total'             => new FieldValue( 'fee_total', $this->format_amount( $this->get_fee_total( $order ) ) ),
			'order_total'           => new FieldValue( 'order_total', $this->format_amount( (float) $order->get_total() ) ),
		];

		return $fields->filter_values( $values );
	}

	/**
	 * Checks whether this provider resolves the given order field.
	 *
	 * @param Field $field Export field definition.
	 */
	private function is_supported_order_field( Field $field ): bool {
		return in_array( $field->id, self::SUPPORTED_FIELD_IDS, true );
	}

	/**
	 * Gets the total fee amount for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_fee_total( WC_Order $order ): float {
		$total = 0.0;

		foreach ( $order->get_fees() as $fee ) {
			$total += (float) $fee->get_total();
		}

		return $total;
	}

	/**
	 * Gets the shipping phone number when supported by the installed WooCommerce version.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	private function get_shipping_phone( WC_Order $order ): string {
		if ( ! method_exists( $order, 'get_shipping_phone' ) ) {
			return '';
		}

		return $order->get_shipping_phone();
	}

	/**
	 * Splits the billing address into street and house number where possible.
	 *
	 * @param string $address Address line 1.
	 *
	 * @return array{street: string, house_number: string}
	 */
	private function split_street_and_house_number( string $address ): array {
		$address = trim( $address );

		if ( preg_match( '/^(?P<street>.*?)[\s,]+(?P<number>\d+[a-zA-Z]?([\s\/-]*\d+[a-zA-Z]?)?)$/', $address, $matches ) ) {
			return [
				'street'       => trim( (string) $matches['street'] ),
				'house_number' => trim( (string) $matches['number'] ),
			];
		}

		return [
			'street'       => $address,
			'house_number' => '',
		];
	}

	/**
	 * Formats a WooCommerce date for export output.
	 */
	private function format_datetime( ?WC_DateTime $date ): string {
		if ( null === $date ) {
			return '';
		}

		return $date->date( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats an amount for export output.
	 */
	private function format_amount( float $amount ): string {
		return number_format( $amount, 2, '.', '' );
	}
}
