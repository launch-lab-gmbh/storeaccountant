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

namespace StoreAccountant\Tax\Contract;

use WC_Order;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides selectable order tax field definitions and values.
 */
interface OrderTaxFieldProviderInterface extends RegistryItemInterface {
	/**
	 * Gets the provider label.
	 */
	public function get_label(): string;

	/**
	 * Gets order tax field definitions.
	 *
	 * @param ExportContext $context Export context.
	 *
	 * @return array<string, Field>
	 */
	public function get_fields( ExportContext $context ): array;

	/**
	 * Gets order tax field values.
	 *
	 * @param WC_Order      $order   WooCommerce order.
	 * @param ExportContext $context Export context.
	 *
	 * @return array<string, FieldValue>
	 */
	public function get_values( WC_Order $order, ExportContext $context ): array;
}
