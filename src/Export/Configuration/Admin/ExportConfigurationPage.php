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

namespace StoreAccountant\Export\Configuration\Admin;

use WP_Error;
use WP_Post;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Configuration\ExportConfigurationRepository;
use StoreAccountant\Export\Configuration\ExportConfigurationTabProviderRegistry;
use StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface;
use StoreAccountant\Export\Contract\ExportTypeAwareInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function array_key_first;
use function array_merge;
use function is_numeric;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders export configuration admin pages.
 */
final readonly class ExportConfigurationPage implements HookRegistrarInterface {
	/**
	 * Initializes the page.
	 *
	 * @param ExportConfigurationPageForm                  $form            Configuration form.
	 * @param ExportConfigurationRepository                $repository      Configuration repository.
	 * @param StorageAdapterRegistry                       $storage_adapters storage adapter registry.
	 * @param ExportAdapterRegistry                        $export_adapters Export adapter registry.
	 * @param ExportRendererRegistry                       $export_writers  Export writer registry.
	 * @param ExportConfigurationFormFieldProviderRegistry $field_providers Additional field providers.
	 * @param ExportFilterFieldProviderRegistry            $filter_field_providers Export filter field providers.
	 * @param AccountingHeaderBar                          $header_bar      Accounting header bar.
	 * @param ExportConfigurationTabProviderRegistry       $tab_providers   Tab provider registry.
	 * @param OrderTaxFieldProviderField                   $tax_field_provider_field Order tax provider field.
	 * @param PermissionChecker                            $permissions     Permission checker.
	 * @param DownloadPasswordManager                      $passwords       Download password manager.
	 */
	public function __construct(
		private ExportConfigurationPageForm $form,
		private ExportConfigurationRepository $repository,
		private StorageAdapterRegistry $storage_adapters,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $export_writers,
		private ExportConfigurationFormFieldProviderRegistry $field_providers,
		private ExportFilterFieldProviderRegistry $filter_field_providers,
		private AccountingHeaderBar $header_bar,
		private ExportConfigurationTabProviderRegistry $tab_providers,
		private OrderTaxFieldProviderField $tax_field_provider_field,
		private PermissionChecker $permissions,
		private DownloadPasswordManager $passwords
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_head', [ $this, 'remove_hidden_submenu_page' ] );
		add_action( 'admin_post_storeaccountant_save_export_configuration', [ $this, 'handle_save' ] );
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10, 2 );
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ] );
	}

	/**
	 * Adds hidden plugin pages used by the configuration list action buttons.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			AccountingMenu::MENU_SLUG,
			__( 'Create Export Configuration', 'storeaccountant' ),
			__( 'Create Export Configuration', 'storeaccountant' ),
			StoreAccountantCapabilities::ACCESS_ADMIN,
			'storeaccountant-export-configuration',
			[ $this, 'render' ]
		);
	}

	/**
	 * Removes hidden plugin pages from the visible accounting submenu after access checks.
	 */
	public function remove_hidden_submenu_page(): void {
		remove_submenu_page( AccountingMenu::MENU_SLUG, 'storeaccountant-export-configuration' );
	}

	/**
	 * Renders the configuration form page.
	 */
	public function render(): void {
		$configuration = $this->get_edit_configuration();
		$active_tab    = $this->get_active_tab( $configuration );
		$read_only     = $this->is_read_mode();

		if ( null === $configuration && ! $this->permissions->can( PermissionActionIds::CONFIGURATION_CREATE ) ) {
			wp_die( esc_html__( 'You are not allowed to create export configurations.', 'storeaccountant' ) );
		}

		if ( null !== $configuration && $read_only && ! $this->permissions->can( PermissionActionIds::CONFIGURATION_VIEW, $configuration->ID ) ) {
			wp_die( esc_html__( 'You are not allowed to view this export configuration.', 'storeaccountant' ) );
		}

		if ( null !== $configuration && ! $read_only && ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT, $configuration->ID ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this export configuration.', 'storeaccountant' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_page_title( $configuration, $read_only ) ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php $this->header_bar->render_configuration_detail_actions( $this->get_return_url(), $read_only && $configuration ? $this->get_edit_url( $configuration->ID ) : null, $configuration ? $configuration->ID : null ); ?>
			<?php if ( $configuration ) : ?>
				<?php $this->render_tabs( $configuration, $active_tab, $read_only ); ?>
				<div class="storeaccountant-tab-panel">
					<?php $this->render_active_tab( $configuration, $active_tab, $read_only ); ?>
				</div>
			<?php else : ?>
				<div class="storeaccountant-content-panel">
					<?php $this->form->render( $configuration ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Uses the current configuration mode for the browser title.
	 *
	 * @param string $admin_title Complete admin title.
	 * @param string $title       Static page title.
	 */
	public function filter_admin_title( string $admin_title, string $title ): string {
		if ( ! $this->is_current_plugin_page() ) {
			return $admin_title;
		}

		$page_title = $this->get_page_title( $this->get_title_configuration(), $this->is_read_mode() );

		return str_replace( $title, $page_title, $admin_title );
	}

	/**
	 * Handles configuration form submission.
	 */
	public function handle_save(): void {
		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_CREATE ) && ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT ) ) {
			wp_die( esc_html__( 'You are not allowed to save export configurations.', 'storeaccountant' ) );
		}

		check_admin_referer( 'storeaccountant_save_export_configuration', 'storeaccountant_export_configuration_nonce' );

		$request                 = Request::post_data();
		$configuration_id        = Request::post_int( 'storeaccountant_export_configuration_id' );
		$configuration           = null;
		$title                   = trim( Request::post_text( 'storeaccountant_export_configuration_title' ) );
		$storage_engine          = '';
		$export_adapter          = Request::post_key( 'storeaccountant_export_adapter', OrderExportAdapter::ADAPTER_ID );
		$export_writer           = '';
			$filters             = [];
			$tax_provider_id     = ExtendedOrderTaxFieldProvider::PROVIDER_ID;
			$additional_settings = [];
		$stored_tax_provider_id  = '';
		$batch_size              = $this->get_batch_size_from_request( $request );
		$password                = Request::post_text( 'storeaccountant_configuration_download_password' );
		$redirect_with_error     = function ( string $error = '1' ) use ( $configuration_id ): void {
			$this->redirect_with_error( $error, $configuration_id );
		};

		if ( $configuration_id > 0 ) {
			$configuration = get_post( $configuration_id );

			if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type || ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT, $configuration_id ) ) {
				$redirect_with_error();
			}

			$export_adapter         = $this->get_stored_export_adapter( $configuration_id );
			$stored_tax_provider_id = $this->get_stored_tax_field_provider( $configuration_id );
			$storage_engine         = Request::post_key( 'storeaccountant_storage_engine' );
			$export_writer          = Request::post_key( 'storeaccountant_export_writer', CsvExportRenderer::RENDERER_ID );
			$filters                = $this->get_filter_selections_from_request( $export_adapter, $request );
			$tax_provider_id        = OrderExportAdapter::ADAPTER_ID === $export_adapter
				? $this->tax_field_provider_field->get_provider_id_from_request( $request )
				: ExtendedOrderTaxFieldProvider::PROVIDER_ID;
			$additional_settings    = $this->get_additional_settings( $export_adapter, $request );
		} else {
			if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_CREATE ) ) {
				$redirect_with_error();
			}

			$export_writer  = $this->get_default_export_writer();
			$storage_engine = $this->get_default_storage_engine();
			$filters        = $this->get_default_filter_selections( $export_adapter );
		}

		if ( '' === $title ) {
			$redirect_with_error();
		}

		$configuration_password = $this->passwords->get_configuration_password_for_submission( $password );

		if ( is_wp_error( $configuration_password ) ) {
			$redirect_with_error();
		}

		if ( str_contains( $title, $configuration_password ) ) {
			$redirect_with_error( 'title_contains_password' );
		}

		if ( is_wp_error( $batch_size ) ) {
			$redirect_with_error( 'invalid_batch_size' );
		}

		if ( is_wp_error( $filters ) ) {
			$redirect_with_error();
		}

		if ( OrderExportAdapter::ADAPTER_ID === $export_adapter && '' === $tax_provider_id ) {
			$redirect_with_error();
		}

		if ( null === $this->export_adapters->get( $export_adapter ) ) {
			$redirect_with_error();
		}

		if ( null === $this->export_writers->get( $export_writer ) ) {
			$redirect_with_error();
		}

		if ( ! $this->storage_adapters->is_enabled( $storage_engine ) ) {
			$redirect_with_error();
		}

		if ( is_wp_error( $additional_settings ) ) {
			$redirect_with_error();
		}

		if ( $configuration_id > 0 ) {
			$post_id = $this->repository->update(
				$configuration_id,
				$title,
				$filters,
				$export_adapter,
				$export_writer,
				$storage_engine,
				$additional_settings,
				$batch_size
			);
		} else {
			$post_id = $this->repository->create(
				$title,
				$filters,
				$export_adapter,
				$export_writer,
				$storage_engine,
				$additional_settings,
				$batch_size
			);
		}

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			$redirect_with_error();
		}

		if ( OrderExportAdapter::ADAPTER_ID === $export_adapter ) {
			update_post_meta(
				$post_id,
				ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER,
				$tax_provider_id
			);

			if ( $configuration_id > 0 && $stored_tax_provider_id !== $tax_provider_id ) {
				$this->refresh_order_tax_field_mapping( $post_id );
			}
		} else {
			delete_post_meta( $post_id, ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER );
		}

		$result = $this->passwords->save_configuration_password( $post_id, $password );

		if ( is_wp_error( $result ) ) {
			$redirect_with_error();
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'             => 'storeaccountant-export-configuration',
					'configuration_id' => (string) $post_id,
					'storeaccountant_export_configuration_saved' => (string) $post_id,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Highlights StoreAccountant while rendering hidden StoreAccountant pages.
	 *
	 * @param string $parent_file Parent file.
	 */
	public function filter_parent_file( ?string $parent_file ): string {
		if ( ! $this->is_current_plugin_page() ) {
			return (string) $parent_file;
		}

		return AccountingMenu::MENU_SLUG;
	}

	/**
	 * Highlights the exports submenu while rendering hidden pages.
	 *
	 * @param string $submenu_file Submenu file.
	 */
	public function filter_submenu_file( ?string $submenu_file ): string {
		if ( ! $this->is_current_plugin_page() ) {
			return (string) $submenu_file;
		}

		return 'edit.php?post_type=' . ExportPostType::POST_TYPE;
	}

	/**
	 * Gets and validates additional provider settings.
	 *
	 * @param string               $export_type Export adapter identifier.
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	private function get_additional_settings( string $export_type, array $request ): array|WP_Error {
		$additional_settings = [];

		foreach ( $this->field_providers->get_all() as $provider ) {
			if ( $provider instanceof ExportTypeAwareInterface && ! $provider->supports_export_type( $export_type ) ) {
				continue;
			}

			$settings = $provider->sanitize_settings( $request );
			$result   = $provider->validate_settings( $settings );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$additional_settings[ $provider->get_id() ] = $settings;
		}

		return $additional_settings;
	}

	/**
	 * Gets export filter selections from request data.
	 *
	 * @param string               $export_type Export adapter identifier.
	 * @param array<string, mixed> $request     Request data.
	 *
	 * @return array<int, ExportFilterSelection>|WP_Error
	 */
	private function get_filter_selections_from_request( string $export_type, array $request ): array|WP_Error {
		$filters = [];

		foreach ( $this->filter_field_providers->get_providers( $export_type ) as $provider ) {
			$selection = $provider->get_selection_from_request( $request );

			if ( is_wp_error( $selection ) ) {
				return $selection;
			}

			$filters[] = $selection;
		}

		return $filters;
	}

	/**
	 * Gets the batch size from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	private function get_batch_size_from_request( array $request ): int|WP_Error {
		$raw_batch_size = isset( $request['storeaccountant_export_batch_size'] )
			? wp_unslash( $request['storeaccountant_export_batch_size'] )
			: ExportPostType::DEFAULT_BATCH_SIZE;

		if ( ! is_numeric( $raw_batch_size ) ) {
			return new WP_Error(
				'storeaccountant_export_batch_size_invalid',
				__( 'Enter a numeric batch size of at least 10.', 'storeaccountant' )
			);
		}

		$batch_size = absint( $raw_batch_size );

		if ( $batch_size < ExportPostType::MIN_BATCH_SIZE ) {
			return new WP_Error(
				'storeaccountant_export_batch_size_too_small',
				__( 'Enter a numeric batch size of at least 10.', 'storeaccountant' )
			);
		}

		return $batch_size;
	}

	/**
	 * Gets default filter selections for a newly created configuration.
	 *
	 * @param string $export_type Export adapter identifier.
	 *
	 * @return array<int, ExportFilterSelection>
	 */
	private function get_default_filter_selections( string $export_type ): array {
		$filters = [];

		foreach ( $this->filter_field_providers->get_providers( $export_type ) as $provider ) {
			$filters[] = $provider->get_default_selection();
		}

		return $filters;
	}

	/**
	 * Renders form submission notices.
	 */
	private function render_notice(): void {
		$notice = $this->get_notice();

		if ( 'configuration_saved' === $notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'The export configuration was saved.', 'storeaccountant' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( 'field_mapping_saved' === $notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'The field mapping was saved.', 'storeaccountant' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( 'field_mapping_error' === $notice ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e( 'The field mapping could not be saved.', 'storeaccountant' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( '' === $notice ) {
			return;
		}

		$message = __( 'The export configuration could not be saved.', 'storeaccountant' );

		if ( 'invalid_batch_size' === $notice ) {
			$message = __( 'Enter a numeric batch size of at least 10.', 'storeaccountant' );
		}

		if ( 'title_contains_password' === $notice ) {
			$message = __( 'The configuration title must not contain the download password.', 'storeaccountant' );
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirects back to the form with an error notice.
	 */
	private function redirect_with_error( string $error = '1', int $configuration_id = 0 ): void {
		$args = [
			'page'                                       => 'storeaccountant-export-configuration',
			'storeaccountant_export_configuration_error' => $error,
		];

		if ( $configuration_id > 0 ) {
			$args['configuration_id'] = (string) $configuration_id;
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Checks whether the current admin page belongs to this screen.
	 */
	private function is_current_plugin_page(): bool {
		return 'storeaccountant-export-configuration' === Request::get_key( 'page' );
	}

	/**
	 * Gets the configuration currently being edited.
	 */
	private function get_edit_configuration(): ?WP_Post {
		$configuration_id = Request::get_int( 'configuration_id' );

		if ( $configuration_id <= 0 ) {
			return null;
		}

		$configuration = get_post( $configuration_id );

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type || ! $this->permissions->can( $this->is_read_mode() ? PermissionActionIds::CONFIGURATION_VIEW : PermissionActionIds::CONFIGURATION_EDIT, $configuration_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this export configuration.', 'storeaccountant' ) );
		}

		return $configuration;
	}

	/**
	 * Gets the configuration used for non-blocking title rendering.
	 */
	private function get_title_configuration(): ?WP_Post {
		$configuration_id = Request::get_int( 'configuration_id' );

		if ( $configuration_id <= 0 ) {
			return null;
		}

		$configuration = get_post( $configuration_id );

		if ( ! $configuration instanceof WP_Post || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type ) {
			return null;
		}

		return $configuration;
	}

	/**
	 * Gets the return URL for the create page.
	 */
	private function get_return_url(): ?string {
		$return_to = Request::get_key( 'return_to' );

		if ( 'config-export' !== $return_to ) {
			return null;
		}

		return add_query_arg(
			[
				'post_type' => ExportPostType::POST_TYPE,
			],
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Gets the page title.
	 *
	 * @param WP_Post|null $configuration Current configuration.
	 */
	private function get_page_title( ?WP_Post $configuration, bool $read_only = false ): string {
		if ( null === $configuration ) {
			return __( 'Create Export Configuration', 'storeaccountant' );
		}

		return sprintf(
			/* translators: %s: export configuration title */
			$read_only ? __( 'Export Configuration "%s"', 'storeaccountant' ) : __( 'Edit Export Configuration "%s"', 'storeaccountant' ),
			get_the_title( $configuration )
		);
	}

	/**
	 * Gets the stored export adapter for an existing configuration.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_stored_export_adapter( int $configuration_id ): string {
		$export_adapter = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_EXPORT_ADAPTER, true );

		if ( '' === $export_adapter ) {
			return OrderExportAdapter::ADAPTER_ID;
		}

		return $export_adapter;
	}

	/**
	 * Gets the active detail tab.
	 *
	 * @param WP_Post|null $configuration Current configuration.
	 */
	private function get_active_tab( ?WP_Post $configuration ): string {
		$tab = Request::get_key( 'tab', 'configuration' );

		if ( ! $configuration || 'configuration' === $tab ) {
			return 'configuration';
		}

		return array_key_exists( $tab, $this->get_tabs( $configuration ) ) ? $tab : 'configuration';
	}

	/**
	 * Renders detail tabs.
	 *
	 * @param WP_Post $configuration Current configuration.
	 * @param string  $active_tab    Active tab identifier.
	 */
	private function render_tabs( WP_Post $configuration, string $active_tab, bool $read_only = false ): void {
		$tabs = $this->get_tabs( $configuration );
		?>
		<nav class="nav-tab-wrapper storeaccountant-export-configuration-tabs" aria-label="<?php esc_attr_e( 'Export configuration sections', 'storeaccountant' ); ?>">
			<?php foreach ( $tabs as $tab_id => $label ) : ?>
				<a
					class="nav-tab <?php echo esc_attr( $active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>"
					href="<?php echo esc_url( $this->get_tab_url( $configuration->ID, $tab_id, $read_only ) ); ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the active detail tab.
	 *
	 * @param WP_Post $configuration Current configuration.
	 * @param string  $active_tab    Active tab identifier.
	 */
	private function render_active_tab( WP_Post $configuration, string $active_tab, bool $read_only = false ): void {
		if ( 'configuration' === $active_tab ) {
			$this->form->render( $configuration, $read_only );
			return;
		}

		$tab_read_only = $read_only || ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING, $configuration->ID );

		foreach ( $this->get_supported_tab_providers( $configuration ) as $provider ) {
			if ( array_key_exists( $active_tab, $provider->get_tabs( $configuration ) ) ) {
				$provider->render( $active_tab, $configuration, $tab_read_only );
				return;
			}
		}
	}

	/**
	 * Gets all available tabs for a configuration.
	 *
	 * @param WP_Post $configuration Current configuration.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs( WP_Post $configuration ): array {
		$tabs = [
			'configuration' => __( 'Configuration', 'storeaccountant' ),
		];

		foreach ( $this->get_supported_tab_providers( $configuration ) as $provider ) {
			$tabs = array_merge( $tabs, $provider->get_tabs( $configuration ) );
		}

		return $tabs;
	}

	/**
	 * Gets tab providers that support the current configuration.
	 *
	 * @param WP_Post $configuration Current configuration.
	 *
	 * @return array<int, ExportConfigurationTabProviderInterface>
	 */
	private function get_supported_tab_providers( WP_Post $configuration ): array {
		$providers = [];

		foreach ( $this->tab_providers->get_all() as $provider ) {
			if ( $provider->supports( $configuration ) ) {
				$providers[] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * Rewrites only order tax field mapping after the selected tax provider changed.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 */
	private function refresh_order_tax_field_mapping( int $configuration_id ): void {
		$configuration = get_post( $configuration_id );

		if ( ! $configuration instanceof WP_Post ) {
			return;
		}

		foreach ( $this->get_supported_tab_providers( $configuration ) as $provider ) {
			if ( $provider instanceof OrderFieldMappingTabProvider ) {
				$provider->refresh_tax_field_mapping( $configuration_id );
				return;
			}
		}
	}

	/**
	 * Gets the stored order tax field provider ID.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_stored_tax_field_provider( int $configuration_id ): string {
		return $this->tax_field_provider_field->get_provider_id_from_configuration( $configuration_id );
	}

	/**
	 * Gets the default export writer for new minimal configurations.
	 */
	private function get_default_export_writer(): string {
		if ( null !== $this->export_writers->get( CsvExportRenderer::RENDERER_ID ) ) {
			return CsvExportRenderer::RENDERER_ID;
		}

		$export_writers = $this->export_writers->get_all();
		$first_writer   = array_key_first( $export_writers );

		return null !== $first_writer ? (string) $first_writer : '';
	}

	/**
	 * Gets the default storage engine for new minimal configurations.
	 */
	private function get_default_storage_engine(): string {
		$storage_adapters = $this->storage_adapters->get_enabled();
		$first_adapter    = array_key_first( $storage_adapters );

		return null !== $first_adapter ? (string) $first_adapter : '';
	}

	/**
	 * Gets a tab URL.
	 *
	 * @param int    $configuration_id Configuration post ID.
	 * @param string $tab              Tab identifier.
	 */
	private function get_tab_url( int $configuration_id, string $tab, bool $read_only = false ): string {
		$args = [
			'page'             => 'storeaccountant-export-configuration',
			'configuration_id' => (string) $configuration_id,
		];

		if ( $read_only ) {
			$args['view'] = '1';
		}

		if ( 'configuration' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Gets the configuration edit URL.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_edit_url( int $configuration_id ): string {
		return add_query_arg(
			[
				'page'             => 'storeaccountant-export-configuration',
				'configuration_id' => (string) $configuration_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Checks whether the current configuration page should render read-only.
	 */
	private function is_read_mode(): bool {
		return '1' === Request::get_key( 'view' );
	}

	/**
	 * Gets the current redirect notice code.
	 */
	private function get_notice(): string {
		if ( Request::has_get( 'storeaccountant_export_configuration_saved' ) ) {
			return 'configuration_saved';
		}

		if ( Request::has_get( 'storeaccountant_field_mapping_saved' ) ) {
			return 'field_mapping_saved';
		}

		if ( Request::has_get( 'storeaccountant_field_mapping_error' ) ) {
			return 'field_mapping_error';
		}

		if ( ! Request::has_get( 'storeaccountant_export_configuration_error' ) ) {
			return '';
		}

		return Request::get_key( 'storeaccountant_export_configuration_error' );
	}
}
