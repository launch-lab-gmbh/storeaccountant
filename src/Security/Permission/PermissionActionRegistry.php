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

namespace StoreAccountant\Security\Permission;

use StoreAccountant\Registry;
use StoreAccountant\Security\Permission\Contract\PermissionActionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects permission actions registered by StoreAccountant and extensions.
 */
final readonly class PermissionActionRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_permission_action';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return PermissionActionInterface::class;
	}
}
