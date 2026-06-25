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

namespace StoreAccountant;

use function sanitize_text_field;
use function str_replace;
use function ucwords;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin translations.
 */
final readonly class I18n {
	/**
	 * Translates a built-in dynamic registry label or formats an extension ID.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $prefix Registry label prefix.
	 * @param string $id     Registry item ID.
	 */
	public static function translate_registry_label( string $prefix, string $id ): string {
		$key = $prefix . $id;

		return match ( $key ) {
			'export_adapter_orders' => __( 'export_adapter_orders', 'storeaccountant' ),
			'export_adapter_customers' => __( 'export_adapter_customers', 'storeaccountant' ),
			'export_adapter_products' => __( 'export_adapter_products', 'storeaccountant' ),
			'exporter_csv' => __( 'exporter_csv', 'storeaccountant' ),
			'exporter_json' => __( 'exporter_json', 'storeaccountant' ),
			'invoice_plugin_woocommerce-pdf-invoices-packing-slips' => __( 'invoice_plugin_woocommerce-pdf-invoices-packing-slips', 'storeaccountant' ),
			'storage_adapter_local' => __( 'storage_adapter_local', 'storeaccountant' ),
			default => ucwords( str_replace( [ '_', '-' ], ' ', sanitize_text_field( $id ) ) ),
		};
	}
}
