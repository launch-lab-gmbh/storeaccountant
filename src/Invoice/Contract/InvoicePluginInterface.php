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

namespace StoreAccountant\Invoice\Contract;

use WC_Order;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Invoice\InvoiceFileType;
use StoreAccountant\Storage\StorageFile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes an installed invoice plugin integration.
 */
interface InvoicePluginInterface extends RegistryItemInterface {
	/**
	 * Checks whether the invoice plugin is available.
	 */
	public function is_active(): bool;

	/**
	 * Gets the invoice number for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function get_invoice_number( WC_Order $order ): string;

	/**
	 * Gets the invoice date for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function get_invoice_date( WC_Order $order ): string;

	/**
	 * Gets available invoice file types.
	 *
	 * @return array<int, InvoiceFileType>
	 */
	public function get_invoice_file_types(): array;

	/**
	 * Gets the invoice file name for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $type  Invoice file type.
	 */
	public function get_invoice_file_name( WC_Order $order, string $type ): string;

	/**
	 * Gets the invoice file for an order when available.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param string   $type  Invoice file type.
	 */
	public function get_invoice_file( WC_Order $order, string $type ): ?StorageFile;
}
