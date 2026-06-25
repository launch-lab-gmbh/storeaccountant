<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Uninstall\Contract;

use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a database cleanup task that runs only during plugin uninstall.
 */
interface UninstallCleanupTaskInterface extends RegistryItemInterface {
	/**
	 * Removes uninstall-scoped database artifacts.
	 */
	public function cleanup(): void;
}
