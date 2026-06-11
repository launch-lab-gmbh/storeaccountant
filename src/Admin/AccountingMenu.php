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

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use function __;
use function add_action;
use function add_menu_page;
use function add_query_arg;
use function add_submenu_page;
use function admin_url;
use function current_user_can;
use function esc_html_e;
use function remove_submenu_page;
use function wp_safe_redirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the StoreAccountant top-level admin menu.
 */
final readonly class AccountingMenu implements HookRegistrarInterface {
	public const MENU_SLUG = 'storeaccountant-accounting';

	public function __construct(
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 9 );
	}

	/**
	 * Registers the top-level accounting menu and visible export entry.
	 */
	public function register_menu(): void {
		if ( ! current_user_can( StoreAccountantCapabilities::ACCESS_ADMIN ) ) {
			return;
		}

		add_menu_page(
			__( 'Accounting', 'storeaccountant' ),
			__( 'Accounting', 'storeaccountant' ),
			StoreAccountantCapabilities::ACCESS_ADMIN,
			self::MENU_SLUG,
			[ $this, 'render' ],
			'dashicons-media-spreadsheet',
			56
		);

		if ( $this->permissions->can( PermissionActionIds::EXPORT_LIST ) ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Exports', 'storeaccountant' ),
				__( 'Exports', 'storeaccountant' ),
				$this->permissions->get_capability( PermissionActionIds::EXPORT_LIST, StoreAccountantCapabilities::READ_EXPORTS ),
				'edit.php?post_type=' . ExportPostType::POST_TYPE
			);
		} elseif ( $this->permissions->can( PermissionActionIds::CONFIGURATION_LIST ) ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Export Configurations', 'storeaccountant' ),
				__( 'Export Configurations', 'storeaccountant' ),
				$this->permissions->get_capability( PermissionActionIds::CONFIGURATION_LIST, StoreAccountantCapabilities::READ_CONFIGURATIONS ),
				'edit.php?post_type=' . ExportConfigurationPostType::POST_TYPE
			);
		}

		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Renders the top-level fallback page.
	 */
	public function render(): void {
		if ( $this->permissions->can( PermissionActionIds::EXPORT_LIST ) ) {
			wp_safe_redirect( $this->get_exports_url() );
			exit;
		}

		if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_LIST ) ) {
			wp_safe_redirect( $this->get_configurations_url() );
			exit;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Accounting', 'storeaccountant' ); ?></h1>
			<p>
				<?php esc_html_e( 'You do not have access to any StoreAccountant overview.', 'storeaccountant' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Gets the export list URL.
	 */
	private function get_exports_url(): string {
		return add_query_arg(
			'post_type',
			ExportPostType::POST_TYPE,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Gets the export configuration list URL.
	 */
	private function get_configurations_url(): string {
		return add_query_arg(
			'post_type',
			ExportConfigurationPostType::POST_TYPE,
			admin_url( 'edit.php' )
		);
	}
}
