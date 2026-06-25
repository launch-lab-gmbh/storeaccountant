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
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides export fields for custom WooCommerce order meta.
 */
final readonly class OrderMetaFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID     = 'order_meta';
	public const FIELD_ID_PREFIX = 'order_meta_';
	public const OPTION_META_KEY = MetaField::OPTION_META_KEY;

	/**
	 * WooCommerce order meta keys already represented by dedicated export fields.
	 *
	 * @var array<int, string>
	 */
	private const RESERVED_META_KEYS = [
		'_billing_first_name',
		'_billing_last_name',
		'_billing_company',
		'_billing_address_1',
		'_billing_address_2',
		'_billing_city',
		'_billing_state',
		'_billing_postcode',
		'_billing_country',
		'_billing_email',
		'_billing_phone',
		'_shipping_first_name',
		'_shipping_last_name',
		'_shipping_company',
		'_shipping_address_1',
		'_shipping_address_2',
		'_shipping_city',
		'_shipping_state',
		'_shipping_postcode',
		'_shipping_country',
		'_shipping_phone',
		'_billing_address_index',
		'_cart_discount',
		'_cart_discount_tax',
		'_coupons_hash',
		'_customer_ip_address',
		'_customer_user',
		'_customer_user_agent',
		'_debug_log_source_pending_deletion',
		'_edit_lock',
		'_fees_hash',
		'_order_currency',
		'_order_key',
		'_order_shipping',
		'_order_shipping_tax',
		'_order_tax',
		'_order_total',
		'_paid_date',
		'_payment_method',
		'_payment_method_title',
		'_prices_include_tax',
		'_recorded_coupon_usage_counts',
		'_recorded_sales',
		'_shipping_address_index',
		'_shipping_hash',
		'_taxes_hash',
		'_transaction_id',
		'_wc_order_attribution_device_type',
		'_wc_order_attribution_session_count',
		'_wc_order_attribution_session_entry',
		'_wc_order_attribution_session_pages',
		'_wc_order_attribution_session_start_time',
		'_wc_order_attribution_source_type',
		'_wc_order_attribution_user_agent',
		'_wcpdf_invoice_creation_trigger',
		'_wcpdf_invoice_date',
		'_wcpdf_invoice_date_formatted',
		'_wcpdf_invoice_display_date',
		'_wcpdf_invoice_number',
		'_wcpdf_invoice_number_data',
		'_wcpdf_invoice_settings',
		'_wcpdf_invoice_type',
	];

	/**
	 * Initializes the order meta field provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param MetaFieldCollector $collector Metadata field collector.
	 */
	public function __construct(
		private MetaFieldCollector $collector
	) {}

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
		return $this->collector->get_fields( $context, self::FIELD_ID_PREFIX, self::RESERVED_META_KEYS );
	}

	/**
	 * Gets the stable export field ID for an order meta key.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $meta_key WooCommerce order meta key.
	 */
	public static function get_field_id( string $meta_key ): string {
		return MetaField::get_field_id( self::FIELD_ID_PREFIX, $meta_key );
	}
}
