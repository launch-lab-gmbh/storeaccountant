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

namespace StoreAccountant\Invoice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes one invoice file type provided by an invoice plugin.
 */
final readonly class InvoiceFileType {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		public string $id,
		public string $label
	) {}
}
