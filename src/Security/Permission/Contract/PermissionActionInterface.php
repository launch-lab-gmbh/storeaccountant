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

namespace StoreAccountant\Security\Permission\Contract;

use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a permission-controlled StoreAccountant admin action.
 */
interface PermissionActionInterface extends RegistryItemInterface {
	/**
	 * Gets the human-readable action label.
	 */
	public function get_label(): string;

	/**
	 * Gets the human-readable action group label.
	 */
	public function get_group(): string;

	/**
	 * Gets the WordPress capability required for this action.
	 */
	public function get_capability(): string;

	/**
	 * Gets an optional description for settings UIs.
	 */
	public function get_description(): string;
}
