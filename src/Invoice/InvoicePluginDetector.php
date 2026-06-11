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

use StoreAccountant\Invoice\Contract\InvoicePluginInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the enabled invoice plugin integration.
 */
final readonly class InvoicePluginDetector {
	/**
	 * Initializes the detector.
	 *
	 * @param InvoicePluginRegistry $plugins Invoice plugin registry.
	 */
	public function __construct(
		private InvoicePluginRegistry $plugins
	) {}

	/**
	 * Gets the enabled invoice plugin.
	 */
	public function get_enabled(): ?InvoicePluginInterface {
		return $this->plugins->get_enabled();
	}

	/**
	 * Checks whether an invoice plugin is enabled.
	 */
	public function is_enabled(): bool {
		return null !== $this->get_enabled();
	}
}
