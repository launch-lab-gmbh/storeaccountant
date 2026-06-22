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

namespace StoreAccountant\Admin\Contract;

use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a tab on the main Accounting overview.
 */
interface AccountingOverviewTabProviderInterface extends RegistryItemInterface {
	/**
	 * Gets the translated tab label.
	 */
	public function get_label(): string;

	/**
	 * Gets the tab target URL.
	 */
	public function get_url(): string;

	/**
	 * Gets whether the tab should be visible to the current user.
	 */
	public function is_visible(): bool;

	/**
	 * Gets the sorting priority for the tab.
	 */
	public function get_priority(): int;
}
