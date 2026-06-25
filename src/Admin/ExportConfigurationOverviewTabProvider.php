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
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
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
 * Provides the export configurations overview tab.
 */
final readonly class ExportConfigurationOverviewTabProvider implements AccountingOverviewTabProviderInterface, HookRegistrarInterface {
	public const TAB_ID   = ExportConfigurationPostType::POST_TYPE;
	public const PRIORITY = 20;

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private PermissionChecker $permissions
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
		return __( 'Export Configurations', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_url(): string {
		return add_query_arg(
			'post_type',
			ExportConfigurationPostType::POST_TYPE,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function is_visible(): bool {
		return $this->permissions->can( PermissionActionIds::CONFIGURATION_LIST );
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
