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
 * Defines a registry that provides objects.
 */
interface RegistryInterface {
	/**
	 * Gets one registered object by identifier.
	 *
	 * @param string $id Registered object identifier.
	 */
	public function get( string $id ): ?object;

	/**
	 * Gets all registered objects keyed by identifier.
	 *
	 * Registries may collect objects through WordPress hooks, so implementations
	 * should validate external values and return only supported objects keyed by
	 * their stable identifier.
	 *
	 * @return array<string, object>
	 */
	public function get_all(): array;
}
