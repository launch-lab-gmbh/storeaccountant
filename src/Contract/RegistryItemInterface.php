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

namespace StoreAccountant\Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines an item that can be collected by a registry.
 */
interface RegistryItemInterface {
	/**
	 * Gets the stable registry identifier.
	 */
	public function get_id(): string;
}
