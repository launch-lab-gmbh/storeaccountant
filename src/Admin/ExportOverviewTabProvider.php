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
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function __;
use function add_filter;
use function add_query_arg;
use function admin_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the exports overview tab.
 */
final readonly class ExportOverviewTabProvider implements AccountingOverviewTabProviderInterface, HookRegistrarInterface {
	public const TAB_ID   = ExportPostType::POST_TYPE;
	public const PRIORITY = 10;

	public function __construct(
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_id(): string {
		return self::TAB_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label(): string {
		return __( 'Exports', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_url(): string {
		return add_query_arg(
			'post_type',
			ExportPostType::POST_TYPE,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_visible(): bool {
		return $this->permissions->can( PermissionActionIds::EXPORT_LIST );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_priority(): int {
		return self::PRIORITY;
	}
}
