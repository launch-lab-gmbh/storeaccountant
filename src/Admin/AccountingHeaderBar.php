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
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function add_query_arg;
use function admin_url;
use function esc_attr;
use function esc_attr__;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function get_posts;
use function get_the_title;
use function implode;
use function strcmp;
use function usort;
use function wp_nonce_field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the accounting admin action header.
 */
final readonly class AccountingHeaderBar {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private PermissionChecker $permissions,
		private AccountingOverviewTabProviderRegistry $tab_providers
	) {}

	/**
	 * Renders overview action buttons.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_overview_actions(): void {
		?>
		<?php $this->render_global_actions(); ?>
		<?php $this->render_list_tabs( ExportOverviewTabProvider::TAB_ID ); ?>
		<?php if ( $this->permissions->can( PermissionActionIds::EXPORT_CREATE ) ) : ?>
			<div class="storeaccountant-headerbar">
				<form class="storeaccountant-export-create-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="storeaccountant_start_export_from_overview" />
					<?php wp_nonce_field( 'storeaccountant_start_export_from_overview', 'storeaccountant_export_overview_nonce' ); ?>
					<label class="screen-reader-text" for="storeaccountant-export-create-selection">
						<?php esc_html_e( 'Select export to create', 'storeaccountant' ); ?>
					</label>
					<select id="storeaccountant-export-create-selection" name="storeaccountant_export_create_selection">
						<option value="quick"><?php esc_html_e( 'Quick Export', 'storeaccountant' ); ?></option>
						<?php $this->render_export_configuration_options(); ?>
					</select>
					<label class="screen-reader-text" for="storeaccountant-export-create-title">
						<?php esc_html_e( 'Export Name', 'storeaccountant' ); ?>
					</label>
					<input
						type="text"
						id="storeaccountant-export-create-title"
						name="storeaccountant_export_title"
						class="regular-text storeaccountant-export-create-title storeaccountant-is-hidden"
						value=""
						placeholder="<?php echo esc_attr__( 'Export Name', 'storeaccountant' ); ?>"
						disabled="disabled"
						data-storeaccountant-configuration-export-title="1"
					/>
					<button type="submit" class="button">
						<?php esc_html_e( 'Create New Export', 'storeaccountant' ); ?>
						<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					</button>
				</form>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders export configuration overview action buttons.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_configuration_actions(): void {
		?>
		<?php $this->render_global_actions(); ?>
			<?php $this->render_list_tabs( ExportConfigurationOverviewTabProvider::TAB_ID ); ?>
			<?php if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_CREATE ) ) : ?>
			<div class="storeaccountant-headerbar">
			<a class="button" href="<?php echo esc_url( $this->get_configuration_page_url() ); ?>">
				<?php esc_html_e( 'Create New Export Configuration', 'storeaccountant' ); ?>
				<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
			</a>
		</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders support page action buttons.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_support_actions(): void {
		?>
		<?php $this->render_global_actions(); ?>
		<?php $this->render_list_tabs( SupportOverviewTabProvider::TAB_ID ); ?>
		<?php
	}

	/**
	 * Renders global accounting action buttons.
	 */
	private function render_global_actions(): void {
		?>
		<div class="storeaccountant-global-actions">
			<?php if ( $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) || $this->permissions->can( PermissionActionIds::MANAGE_PERMISSIONS ) ) : ?>
				<a class="button" href="<?php echo esc_url( $this->get_settings_url() ); ?>">
					<?php esc_html_e( 'Configure Accounting Plugin', 'storeaccountant' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders hidden page action buttons.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_detail_actions(): void {
		?>
		<div class="storeaccountant-headerbar">
			<a class="button" href="<?php echo esc_url( $this->get_overview_url() ); ?>">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Accounting Overview', 'storeaccountant' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Renders export configuration detail action buttons.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_configuration_detail_actions( ?string $return_url = null, ?string $edit_url = null, ?int $configuration_id = null ): void {
		$return_url = $return_url ?? $this->get_configuration_overview_url();
		?>
		<div class="storeaccountant-headerbar">
			<a class="button" href="<?php echo esc_url( $return_url ); ?>">
				<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Back to Export Configurations', 'storeaccountant' ); ?>
			</a>
			<?php if ( null !== $edit_url && null !== $configuration_id && $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT, $configuration_id ) ) : ?>
				<a class="button button-primary" href="<?php echo esc_url( $edit_url ); ?>">
					<?php esc_html_e( 'Edit Export Configuration', 'storeaccountant' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Gets the accounting export overview URL.
	 */
	private function get_overview_url(): string {
		return add_query_arg(
			[
				'post_type' => ExportPostType::POST_TYPE,
			],
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Gets the export configuration overview URL.
	 */
	private function get_configuration_overview_url(): string {
		return add_query_arg(
			[
				'post_type' => ExportConfigurationPostType::POST_TYPE,
			],
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Renders saved export configuration options for the export create selector.
	 */
	private function render_export_configuration_options(): void {
		$configurations = get_posts(
			[
				'post_type'      => ExportConfigurationPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		if ( [] === $configurations ) {
			return;
		}
		?>
		<option value="" disabled="disabled">----------</option>
		<?php foreach ( $configurations as $configuration ) : ?>
			<option value="<?php echo esc_attr( 'configuration:' . (string) $configuration->ID ); ?>">
				<?php echo esc_html( get_the_title( $configuration ) ); ?>
			</option>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Gets the export configuration create page URL.
	 */
	private function get_configuration_page_url(): string {
		return add_query_arg(
			[
				'page' => 'storeaccountant-export-configuration',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Gets the plugin settings URL.
	 */
	private function get_settings_url(): string {
		return add_query_arg(
			'page',
			'storeaccountant-settings',
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Renders list table navigation tabs.
	 *
	 * @param string $active_tab Active tab ID.
	 */
	private function render_list_tabs( string $active_tab ): void {
		$tabs = $this->get_visible_tabs();
		?>
		<nav class="nav-tab-wrapper storeaccountant-list-tabs" aria-label="<?php echo esc_attr__( 'Accounting navigation', 'storeaccountant' ); ?>">
			<?php foreach ( $tabs as $tab ) : ?>
				<a class="<?php echo esc_attr( $this->get_tab_class( $tab->get_id(), $active_tab ) ); ?>" href="<?php echo esc_url( $tab->get_url() ); ?>">
					<?php echo esc_html( $tab->get_label() ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Gets visible overview tabs sorted by priority.
	 *
	 * @return array<int, AccountingOverviewTabProviderInterface>
	 */
	private function get_visible_tabs(): array {
		$tabs = [];

		foreach ( $this->tab_providers->get_all() as $provider ) {
			if ( $provider->is_visible() ) {
				$tabs[] = $provider;
			}
		}

		usort(
			$tabs,
			static function ( AccountingOverviewTabProviderInterface $left, AccountingOverviewTabProviderInterface $right ): int {
				$priority_comparison = $left->get_priority() <=> $right->get_priority();

				if ( 0 !== $priority_comparison ) {
					return $priority_comparison;
				}

				return strcmp( $left->get_id(), $right->get_id() );
			}
		);

		return $tabs;
	}

	/**
	 * Gets a list tab CSS class.
	 *
	 * @param string $tab        Tab ID.
	 * @param string $active_tab Active tab ID.
	 */
	private function get_tab_class( string $tab, string $active_tab ): string {
		$classes = [ 'nav-tab' ];

		if ( $tab === $active_tab ) {
			$classes[] = 'nav-tab-active';
		}

		return implode( ' ', $classes );
	}
}
