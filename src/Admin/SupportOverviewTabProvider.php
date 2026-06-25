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
use StoreAccountant\Contract\HookRegistrarInterface;
use function __;
use function add_filter;
use function add_query_arg;
use function admin_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the support overview tab.
 */
final readonly class SupportOverviewTabProvider implements AccountingOverviewTabProviderInterface, HookRegistrarInterface {
	public const TAB_ID   = 'support';
	public const PRIORITY = 900;

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private AccountingSupportAccess $access
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_accounting_overview_tab_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::TAB_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_label(): string {
		return __( 'Support', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_url(): string {
		return add_query_arg(
			'page',
			AccountingSupportPage::PAGE_SLUG,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function is_visible(): bool {
		return $this->access->can_access();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_priority(): int {
		return self::PRIORITY;
	}
}
