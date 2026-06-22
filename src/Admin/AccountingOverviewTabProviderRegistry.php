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

namespace StoreAccountant\Admin;

use StoreAccountant\Admin\Contract\AccountingOverviewTabProviderInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered Accounting overview tab providers.
 */
final readonly class AccountingOverviewTabProviderRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_accounting_overview_tab_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return AccountingOverviewTabProviderInterface::class;
	}
}
