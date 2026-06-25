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

namespace StoreAccountant\Settings\Admin;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Invoice\Admin\InvoicePluginForm;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Queue\Admin\QueueTransportsSettingsForm;
use StoreAccountant\Security\Admin\PermissionsSettingsForm;
use StoreAccountant\Security\Admin\SecuritySettingsForm;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\RolePermissionRepository;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\Admin\StorageLocationsForm;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_values;
use function implode;
use function sprintf;
use function str_contains;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders plugin settings linked from the plugins overview.
 */
final readonly class PluginSettingsPage implements HookRegistrarInterface {
	private const PAGE_SLUG             = 'storeaccountant-settings';
	private const TAB_STORAGE_LOCATIONS = 'storage-locations';
	private const TAB_INVOICE_PROVIDERS = 'invoice-providers';
	private const TAB_TRANSPORTS        = 'transports';
	private const TAB_PERMISSIONS       = 'permissions';
	private const TAB_SECURITY          = 'security';

	/**
	 * Initializes the settings page.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param StorageAdapterRegistry      $storage_adapters      Storage adapter registry.
	 * @param StorageLocationsForm        $storage_locations_form Storage locations form.
	 * @param InvoicePluginRegistry       $invoice_plugins        Invoice plugin registry.
	 * @param InvoicePluginForm           $invoice_plugin_form    Invoice plugin form.
	 * @param QueueTransportsSettingsForm $queue_transports_form Queue transports form.
	 * @param PermissionsSettingsForm     $permissions_form     Permissions settings form.
	 * @param SecuritySettingsForm        $security_form        Security settings form.
	 * @param PluginSettingsTabProviderRegistry $tab_providers Plugin settings tab provider registry.
	 * @param RolePermissionRepository    $role_permissions    Role permission repository.
	 * @param PermissionChecker           $permissions            Permission checker.
	 * @param DownloadPasswordManager     $passwords              Download password manager.
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private StorageLocationsForm $storage_locations_form,
		private InvoicePluginRegistry $invoice_plugins,
		private InvoicePluginForm $invoice_plugin_form,
		private QueueTransportsSettingsForm $queue_transports_form,
		private PermissionsSettingsForm $permissions_form,
		private SecuritySettingsForm $security_form,
		private PluginSettingsTabProviderRegistry $tab_providers,
		private RolePermissionRepository $role_permissions,
		private PermissionChecker $permissions,
		private DownloadPasswordManager $passwords
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_post_storeaccountant_save_plugin_settings', [ $this, 'handle_save' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( STOREACCOUNTANT_FILE ), [ $this, 'filter_plugin_action_links' ] );
		add_filter( 'plugin_row_meta', [ $this, 'filter_plugin_row_meta' ], 10, 2 );
	}

	/**
	 * Registers the hidden settings page.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register_page(): void {
		add_submenu_page(
			'options.php',
			__( 'StoreAccountant Settings', 'storeaccountant' ),
			__( 'StoreAccountant Settings', 'storeaccountant' ),
			StoreAccountantCapabilities::ACCESS_ADMIN,
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Adds a settings link to the plugin row.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int|string, string> $links Plugin action links.
	 *
	 * @return array<int|string, string>
	 */
	public function filter_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->get_url() ),
			esc_html__( 'Settings', 'storeaccountant' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Adds documentation links to the plugin row metadata.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int|string, string> $links Plugin row metadata links.
	 * @param string                    $file  Plugin file path.
	 *
	 * @return array<int|string, string>
	 */
	public function filter_plugin_row_meta( array $links, string $file ): array {
		if ( plugin_basename( STOREACCOUNTANT_FILE ) !== $file ) {
			return $links;
		}

		$links = array_values(
			array_filter(
				$links,
				static fn ( string $link ): bool => ! str_contains( $link, 'storeaccountant.launch-lab.de' )
			)
		);

		$links[] = $this->plugin_meta_link(
			'https://storeaccountant.launch-lab.de/',
			__( 'To Plugin Website', 'storeaccountant' )
		);
		$links[] = $this->plugin_meta_link(
			__( 'https://storeaccountant.launch-lab.de/en/documentation/', 'storeaccountant' ),
			__( 'Guides', 'storeaccountant' )
		);
		$links[] = $this->plugin_meta_link(
			'https://storeaccountant.launch-lab.de/en/documentation-developer/',
			__( 'Developer Documentation', 'storeaccountant' )
		);
		$links[] = $this->plugin_meta_link(
			'https://github.com/launch-lab-gmbh/storeaccountant',
			__( 'GitHub', 'storeaccountant' )
		);

		return $links;
	}

	/**
	 * Formats a plugin row metadata link.
	 *
	 * @param string $url   Link URL.
	 * @param string $label Link label.
	 */
	private function plugin_meta_link( string $url, string $label ): string {
		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Renders the settings page.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render(): void {
		if ( ! $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) && ! $this->permissions->can( PermissionActionIds::MANAGE_PERMISSIONS ) && ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage StoreAccountant settings.', 'storeaccountant' ) );
		}

		$active_tab = $this->get_active_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'StoreAccountant Settings', 'storeaccountant' ); ?></h1>
				<?php if ( $this->is_settings_saved_notice() ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'StoreAccountant settings were saved.', 'storeaccountant' ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->render_tabs( $active_tab ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="storeaccountant_save_plugin_settings" />
				<input type="hidden" name="storeaccountant_settings_tab" value="<?php echo esc_attr( $active_tab ); ?>" />
				<?php wp_nonce_field( 'storeaccountant_save_plugin_settings', 'storeaccountant_plugin_settings_nonce' ); ?>

				<?php if ( self::TAB_STORAGE_LOCATIONS === $active_tab ) : ?>
					<div class="storeaccountant-tab-panel">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row" colspan="2">
										<h3><?php esc_html_e( 'Storage adapter configuration', 'storeaccountant' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Choose which storage locations can be selected for new exports.', 'storeaccountant' ); ?></p>
									</th>
								</tr>
								<?php $this->storage_locations_form->render_fields(); ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( self::TAB_INVOICE_PROVIDERS === $active_tab ) : ?>
					<div class="storeaccountant-tab-panel">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row" colspan="2">
										<h3><?php esc_html_e( 'Invoice provider configuration', 'storeaccountant' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Choose which active invoice provider should add invoice fields to exports.', 'storeaccountant' ); ?></p>
									</th>
								</tr>
								<?php $this->invoice_plugin_form->render_fields(); ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( self::TAB_TRANSPORTS === $active_tab ) : ?>
					<div class="storeaccountant-tab-panel">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row" colspan="2">
										<h3><?php esc_html_e( 'Queue transport configuration', 'storeaccountant' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Choose how StoreAccountant background jobs are transported before they are processed.', 'storeaccountant' ); ?></p>
									</th>
								</tr>
								<?php $this->queue_transports_form->render_fields(); ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( self::TAB_PERMISSIONS === $active_tab ) : ?>
					<div class="storeaccountant-tab-panel">
						<table class="form-table" role="presentation">
							<tbody>
								<?php $this->permissions_form->render_fields(); ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php if ( self::TAB_SECURITY === $active_tab ) : ?>
					<div class="storeaccountant-tab-panel">
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row" colspan="2">
										<h3><?php esc_html_e( 'Download security configuration', 'storeaccountant' ); ?></h3>
										<p class="description"><?php esc_html_e( 'Manage the global password used for password-protected export downloads.', 'storeaccountant' ); ?></p>
									</th>
								</tr>
								<?php $this->security_form->render_fields(); ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

				<?php $this->render_provider_tab( $active_tab ); ?>

				<p class="submit storeaccountant-settings-submit">
					<a class="button" href="<?php echo esc_url( $this->get_accounting_url() ); ?>">
						<span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'Back to Accounting', 'storeaccountant' ); ?>
					</a>
					<button type="submit" class="button button-primary" name="storeaccountant_save_plugin_settings" id="storeaccountant-save-plugin-settings">
						<?php esc_html_e( 'Save Settings', 'storeaccountant' ); ?>
						<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handles plugin settings submission.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function handle_save(): void {
		check_admin_referer( 'storeaccountant_save_plugin_settings', 'storeaccountant_plugin_settings_nonce' );

		$request    = Request::post_data();
		$active_tab = $this->get_active_tab_from_request( $request );

		if ( self::TAB_PERMISSIONS === $active_tab ) {
			if ( ! $this->permissions->can( PermissionActionIds::MANAGE_PERMISSIONS ) ) {
				wp_die( esc_html__( 'You are not allowed to manage StoreAccountant permissions.', 'storeaccountant' ) );
			}
		} elseif ( ! $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) && ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to manage StoreAccountant settings.', 'storeaccountant' ) );
		}

		if ( self::TAB_STORAGE_LOCATIONS === $active_tab ) {
			$this->storage_adapters->save_enabled( $this->get_enabled_storage_adapters_from_request() );
		}

		if ( self::TAB_SECURITY === $active_tab ) {
			$new_password = Request::post_secret( 'storeaccountant_global_download_password' );

			if ( '' !== $new_password ) {
				$result = $this->passwords->save_global_password( $new_password );

				if ( is_wp_error( $result ) ) {
					wp_die( esc_html( $result->get_error_message() ) );
				}
			}
		}

		if ( self::TAB_INVOICE_PROVIDERS === $active_tab ) {
			$enabled_invoice_plugin = Request::post_key( 'storeaccountant_enabled_invoice_plugin' );

			$this->invoice_plugins->save_enabled( $enabled_invoice_plugin );
		}

		if ( self::TAB_TRANSPORTS === $active_tab ) {
			$this->queue_transports_form->save_from_request( $request );
		}

		if ( self::TAB_PERMISSIONS === $active_tab ) {
			$this->role_permissions->save( $this->permissions_form->get_roles_from_request( $request ) );
		}

		$this->save_provider_tab( $active_tab, $request );

		wp_safe_redirect(
			add_query_arg(
				[
					'tab'                            => $active_tab,
					'storeaccountant_settings_saved' => '1',
				],
				$this->get_url()
			)
		);
		exit;
	}

	/**
	 * Renders settings tabs.
	 *
	 * @param string $active_tab Active tab key.
	 */
	private function render_tabs( string $active_tab ): void {
		$tabs = $this->get_available_tabs();
		?>
		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab => $label ) : ?>
				<a href="<?php echo esc_url( $this->get_tab_url( $tab ) ); ?>" class="<?php echo esc_attr( $this->get_tab_class( $tab, $active_tab ) ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Gets the settings page URL.
	 */
	private function get_url(): string {
		return add_query_arg(
			'page',
			self::PAGE_SLUG,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Gets the accounting overview URL.
	 */
	private function get_accounting_url(): string {
		return add_query_arg(
			'post_type',
			ExportPostType::POST_TYPE,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Gets a settings tab URL.
	 *
	 * @param string $tab Tab key.
	 */
	private function get_tab_url( string $tab ): string {
		return add_query_arg(
			'tab',
			$tab,
			$this->get_url()
		);
	}

	/**
	 * Gets the active settings tab.
	 */
	private function get_active_tab(): string {
		return $this->get_active_tab_from_request(
			[
				'tab' => Request::get_key( 'tab' ),
			]
		);
	}

	/**
	 * Gets the active settings tab from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	private function get_active_tab_from_request( array $request ): string {
		$tab = isset( $request['tab'] ) ? sanitize_key( (string) wp_unslash( $request['tab'] ) ) : '';

		if ( isset( $request['storeaccountant_settings_tab'] ) ) {
			$tab = sanitize_key( (string) wp_unslash( $request['storeaccountant_settings_tab'] ) );
		}

		if ( array_key_exists( $tab, $this->get_available_tabs() ) ) {
			return $tab;
		}

		$tabs = $this->get_available_tabs();
		$tab  = array_key_first( $tabs );

		return null !== $tab ? (string) $tab : self::TAB_STORAGE_LOCATIONS;
	}

	/**
	 * Gets all tabs available to the current user.
	 *
	 * @return array<string, string>
	 */
	private function get_available_tabs(): array {
		$tabs = [];

		if ( $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) ) {
			$tabs[ self::TAB_STORAGE_LOCATIONS ] = __( 'Storage Locations', 'storeaccountant' );
			$tabs[ self::TAB_INVOICE_PROVIDERS ] = __( 'Invoice Providers', 'storeaccountant' );
			$tabs[ self::TAB_TRANSPORTS ]        = __( 'Transports', 'storeaccountant' );
		}

		if ( $this->permissions->can( PermissionActionIds::MANAGE_PERMISSIONS ) ) {
			$tabs[ self::TAB_PERMISSIONS ] = __( 'Permissions', 'storeaccountant' );
		}

		if ( $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) ) {
			$tabs[ self::TAB_SECURITY ] = __( 'Security', 'storeaccountant' );
		}

		if ( $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) || $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) {
			foreach ( $this->tab_providers->get_all() as $provider ) {
				foreach ( $provider->get_tabs() as $tab => $label ) {
					$tab = sanitize_key( $tab );

					if ( $this->is_core_tab( $tab ) ) {
						continue;
					}

					$tabs[ $tab ] = $label;
				}
			}
		}

		return $tabs;
	}

	/**
	 * Renders a provider-backed tab when active.
	 *
	 * @param string $active_tab Active tab key.
	 */
	private function render_provider_tab( string $active_tab ): void {
		if ( ( ! $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) && ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) || $this->is_core_tab( $active_tab ) ) {
			return;
		}

		foreach ( $this->tab_providers->get_all() as $provider ) {
			if ( ! array_key_exists( $active_tab, $this->sanitize_tab_ids( $provider->get_tabs() ) ) ) {
				continue;
			}
			?>
			<div class="storeaccountant-tab-panel">
				<table class="form-table" role="presentation">
					<tbody>
						<?php $provider->render( $active_tab ); ?>
					</tbody>
				</table>
			</div>
			<?php
			return;
		}
	}

	/**
	 * Saves a provider-backed tab when active.
	 *
	 * @param string               $active_tab Active tab key.
	 * @param array<string, mixed> $request    Request data.
	 */
	private function save_provider_tab( string $active_tab, array $request ): void {
		if ( ( ! $this->permissions->can( PermissionActionIds::MANAGE_SETTINGS ) && ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_LOGGING_MANAGE ) ) || $this->is_core_tab( $active_tab ) ) {
			return;
		}

		foreach ( $this->tab_providers->get_all() as $provider ) {
			if ( array_key_exists( $active_tab, $this->sanitize_tab_ids( $provider->get_tabs() ) ) ) {
				$provider->save( $active_tab, $request );
				return;
			}
		}
	}

	/**
	 * Checks whether a tab is rendered by the core settings page.
	 *
	 * @param string $tab Tab key.
	 */
	private function is_core_tab( string $tab ): bool {
		return self::TAB_STORAGE_LOCATIONS === $tab
			|| self::TAB_INVOICE_PROVIDERS === $tab
			|| self::TAB_TRANSPORTS === $tab
			|| self::TAB_PERMISSIONS === $tab
			|| self::TAB_SECURITY === $tab;
	}

	/**
	 * Sanitizes provider tab identifiers.
	 *
	 * @param array<string, string> $tabs Raw tabs.
	 *
	 * @return array<string, string>
	 */
	private function sanitize_tab_ids( array $tabs ): array {
		$sanitized = [];

		foreach ( $tabs as $tab => $label ) {
			$sanitized[ sanitize_key( $tab ) ] = $label;
		}

		return $sanitized;
	}

	/**
	 * Gets the tab CSS class.
	 *
	 * @param string $tab        Tab key.
	 * @param string $active_tab Active tab key.
	 */
	private function get_tab_class( string $tab, string $active_tab ): string {
		$classes = [ 'nav-tab' ];

		if ( $tab === $active_tab ) {
			$classes[] = 'nav-tab-active';
		}

		return implode( ' ', $classes );
	}

	/**
	 * Gets sanitized storage adapter IDs from the settings request.
	 *
	 * @return array<int, string>
	 */
	private function get_enabled_storage_adapters_from_request(): array {
		check_admin_referer( 'storeaccountant_save_plugin_settings', 'storeaccountant_plugin_settings_nonce' );

		$submitted = Request::post_array( 'storeaccountant_enabled_storage_adapters' );
		if ( [] === $submitted ) {
			return [];
		}

		return array_map(
			static fn ( mixed $adapter_id ): string => sanitize_key( (string) wp_unslash( $adapter_id ) ),
			$submitted
		);
	}

	/**
	 * Checks whether the settings saved notice should be shown.
	 */
	private function is_settings_saved_notice(): bool {
		return Request::has_get( 'storeaccountant_settings_saved' );
	}
}
