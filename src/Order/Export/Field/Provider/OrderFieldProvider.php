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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce order export fields.
 */
final readonly class OrderFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'orders';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

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
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( ExportContext $context ): bool {
		return OrderExportAdapter::ADAPTER_ID === $context->export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_fields( ExportContext $context ): array {
		$fields = [
			'order_id'             => new Field( 'order_id', 'order_id', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'order_number'         => new Field( 'order_number', 'order_number' ),
			'order_date'           => new Field( 'order_date', 'order_date', new DateTimeFieldType() ),
			'order_status'         => new Field( 'order_status', 'order_status' ),
			'currency'             => new Field( 'currency', 'currency' ),
			'payment_method'       => new Field( 'payment_method', 'payment_method' ),
			'payment_method_title' => new Field( 'payment_method_title', 'payment_method_title' ),
			'customer_id'          => new Field( 'customer_id', 'customer_id', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
		];

		foreach ( [
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
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id );
		}

		foreach ( [
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
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id );
		}

		foreach ( [
			'order_total',
			'order_subtotal',
			'discount_total',
			'shipping_total',
			'fee_total',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id, new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) );
		}

		return $fields;
	}
}
