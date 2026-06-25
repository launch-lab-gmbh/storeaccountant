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

namespace StoreAccountant\Uninstall;

use StoreAccountant\Registry;
use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides database cleanup tasks registered for plugin uninstall.
 */
final readonly class UninstallCleanupTaskRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_uninstall_cleanup_task';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return UninstallCleanupTaskInterface::class;
	}
}
