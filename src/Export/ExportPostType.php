<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright  LaunchLab GmbH
 * @author     thomas.baier@launch-lab.de
 * @author-uri https://launch-lab.de
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export;

use WP_Post;
use Throwable;
use Symfony\Component\Messenger\MessageBusInterface;
use StoreAccountant\Admin\AdminDateFormatter;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Diagnostic\Admin\DiagnosticIncidentDownloadController;
use StoreAccountant\Diagnostic\DiagnosticIncident;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Export\Admin\ExportListPollingResponseFactory;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
use StoreAccountant\I18n;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function array_filter;
use function array_key_exists;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_merge;
use function count;
use function explode;
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Download responses stream stored files directly to the browser.
use function fclose;
use function implode;
use function is_resource;
use function is_string;
use function ltrim;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function substr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the saved export post type and its admin list columns.
 */
final readonly class ExportPostType implements HookRegistrarInterface {

	public const POST_TYPE = 'storeacct_export';

	public const META_EXPORTED_AT            = '_storeaccountant_exported_at';
	public const META_STATUS                 = '_storeaccountant_status';
	public const META_FILTERS                = '_storeaccountant_filters';
	public const META_STORAGE_ENGINE         = '_storeaccountant_storage_engine';
	public const META_EXPORT_ADAPTER         = '_storeaccountant_export_adapter';
	public const META_EXPORT_WRITER          = '_storeaccountant_export_writer';
	public const META_BATCH_SIZE             = '_storeaccountant_batch_size';
	public const META_PATH                   = '_storeaccountant_path';
	public const META_TRIGGERED_BY           = '_storeaccountant_triggered_by';
	public const META_CONFIGURATION_ID       = '_storeaccountant_configuration_id';
	public const META_TOTAL_ITEMS            = '_storeaccountant_total_items';
	public const META_PROCESSED_ITEMS        = '_storeaccountant_processed_items';
	public const META_TOTAL_BATCHES          = '_storeaccountant_total_batches';
	public const META_PROCESSED_BATCHES      = '_storeaccountant_processed_batches';
	public const META_FAILED_BATCHES         = '_storeaccountant_failed_batches';
	public const META_CURRENT_STEP           = '_storeaccountant_current_step';
	public const META_ERROR_MESSAGE          = '_storeaccountant_error_message';
	public const META_LOG_ENTRIES            = '_storeaccountant_log_entries';
	public const META_STARTED_AT             = '_storeaccountant_started_at';
	public const META_FINISHED_AT            = '_storeaccountant_finished_at';
	public const META_SCHEDULED_FOR          = '_storeaccountant_scheduled_for';
	public const META_DOWNLOAD_TOKEN         = '_storeaccountant_download_token';
	public const META_DOWNLOAD_PASSWORD      = '_storeaccountant_download_password';
	public const META_DOWNLOAD_PASSWORD_HASH = '_storeaccountant_download_password_hash';
	public const MIN_BATCH_SIZE              = 10;
	public const DEFAULT_BATCH_SIZE          = 100;

	/**
	 * Initializes the export post type.
	 *
	 * @param AccountingHeaderBar              $header_bar               Accounting header bar.
	 * @param ExportAdapterRegistry            $adapter_registry         Export adapter registry.
	 * @param ExportRendererRegistry           $writer_registry          Export writer registry.
	 * @param StorageAdapterRegistry           $storage_adapters         storage adapter registry.
	 * @param ExportStoragePathGenerator       $storage_path_generator   Storage path generator.
	 * @param ExportFilterSelectionSerializer  $filter_serializer        Filter selection serializer.
	 * @param ExportReadTabProviderRegistry    $read_tab_providers       Read view tab provider registry.
	 * @param PermissionChecker                $permissions              Permission checker.
	 * @param ExportRepository                 $repository               Export repository.
	 * @param MessageBusInterface              $message_bus              Message bus.
	 * @param QueueLoopbackDispatcher          $loopback_dispatcher      Queue loopback dispatcher.
	 * @param ExportListPollingResponseFactory $polling_response_factory Polling response factory.
	 * @param ExportDownloadUrlFactory         $download_urls            Download URL factory.
	 * @param DiagnosticIncidentLogger         $diagnostics              Diagnostic incident logger.
	 */
	public function __construct(
		private AccountingHeaderBar $header_bar,
		private ExportAdapterRegistry $adapter_registry,
		private ExportRendererRegistry $writer_registry,
		private StorageAdapterRegistry $storage_adapters,
		private ExportStoragePathGenerator $storage_path_generator,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportReadTabProviderRegistry $read_tab_providers,
		private PermissionChecker $permissions,
		private ExportRepository $repository,
		private MessageBusInterface $message_bus,
		private QueueLoopbackDispatcher $loopback_dispatcher,
		private ExportListPollingResponseFactory $polling_response_factory,
		private ExportDownloadUrlFactory $download_urls,
		private DiagnosticIncidentLogger $diagnostics
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_head', [ $this, 'remove_hidden_submenu_page' ] );
		add_action( 'admin_notices', [ $this, 'render_export_notice' ] );
		add_filter( 'views_edit-' . self::POST_TYPE, [ $this, 'render_action_buttons' ] );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'filter_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, [ $this, 'filter_bulk_actions' ] );
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
		add_filter( 'get_edit_post_link', [ $this, 'filter_edit_post_link' ], 10, 3 );
		add_filter( 'map_meta_cap', [ $this, 'filter_meta_cap' ], 10, 4 );
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10, 2 );
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ] );
		add_filter( 'wp_untrash_post_status', [ $this, 'filter_untrash_post_status' ], 10, 3 );
		add_filter( 'display_post_states', [ $this, 'filter_post_states' ], 10, 2 );
		add_action( 'admin_post_storeaccountant_download_export', [ $this, 'handle_download_export_file' ] );
		add_action( 'admin_post_storeaccountant_retry_export', [ $this, 'handle_retry_export' ] );
		add_action( 'load-post.php', [ $this, 'redirect_native_edit_screen' ] );
		add_action( 'load-edit.php', [ $this, 'guard_native_list_screen' ] );
	}

	/**
	 * Adds the hidden export read page used by list table title links.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			AccountingMenu::MENU_SLUG,
			__( 'View Accounting Export', 'storeaccountant' ),
			__( 'View Accounting Export', 'storeaccountant' ),
			$this->permissions->get_capability( PermissionActionIds::EXPORT_VIEW, StoreAccountantCapabilities::VIEW_EXPORT ),
			'storeaccountant-export',
			[ $this, 'render' ]
		);
	}

	/**
	 * Removes the hidden read page from the visible accounting submenu.
	 */
	public function remove_hidden_submenu_page(): void {
		remove_submenu_page( AccountingMenu::MENU_SLUG, 'storeaccountant-export' );
	}

	/**
	 * Registers the saved export post type.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'          => [
					'name'          => __( 'Accounting Exports', 'storeaccountant' ),
					'singular_name' => __( 'Accounting Export', 'storeaccountant' ),
					'menu_name'     => __( 'Exports', 'storeaccountant' ),
					'add_new_item'  => __( 'Add New Accounting Export', 'storeaccountant' ),
					'edit_item'     => __( 'Edit Accounting Export', 'storeaccountant' ),
					'view_item'     => __( 'View Accounting Export', 'storeaccountant' ),
					'search_items'  => __( 'Search Accounting Exports', 'storeaccountant' ),
					'not_found'     => __( 'No accounting exports found.', 'storeaccountant' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'capabilities'    => [
					'create_posts'           => 'do_not_allow',
					'delete_others_posts'    => StoreAccountantCapabilities::DELETE_EXPORTS,
					'delete_posts'           => StoreAccountantCapabilities::DELETE_EXPORTS,
					'delete_private_posts'   => StoreAccountantCapabilities::DELETE_EXPORTS,
					'delete_published_posts' => StoreAccountantCapabilities::DELETE_EXPORTS,
					'edit_others_posts'      => StoreAccountantCapabilities::READ_EXPORTS,
					'edit_posts'             => StoreAccountantCapabilities::READ_EXPORTS,
					'edit_private_posts'     => StoreAccountantCapabilities::READ_EXPORTS,
					'edit_published_posts'   => StoreAccountantCapabilities::READ_EXPORTS,
					'publish_posts'          => StoreAccountantCapabilities::CREATE_EXPORTS,
					'read_private_posts'     => StoreAccountantCapabilities::READ_EXPORTS,
				],
				'map_meta_cap'    => true,
				'supports'        => [ 'title' ],
				'menu_icon'       => 'dashicons-media-spreadsheet',
			]
		);
	}

	/**
	 * Replaces admin list columns for saved exports.
	 *
	 * @param array<string, string> $columns Registered columns.
	 *
	 * @return array<string, string>
	 */
	public function filter_columns( array $columns ): array {
		return [
			'cb'                        => $columns['cb'] ?? '<input type="checkbox" />',
			'title'                     => __( 'Title', 'storeaccountant' ),
			'storeaccountant_progress'  => __( 'Progress', 'storeaccountant' ),
			self::META_EXPORTED_AT      => __( 'Exported At', 'storeaccountant' ),
			self::META_EXPORT_ADAPTER   => __( 'Export Type', 'storeaccountant' ),
			self::META_EXPORT_WRITER    => __( 'Export Format', 'storeaccountant' ),
			self::META_TRIGGERED_BY     => __( 'Triggered By', 'storeaccountant' ),
			self::META_CONFIGURATION_ID => __( 'Configuration', 'storeaccountant' ),
			self::META_PATH             => __( 'Status / Download', 'storeaccountant' ),
		];
	}

	/**
	 * Renders custom admin list column values.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Export post ID.
	 */
	public function render_column( string $column_name, int $post_id ): void {
		if ( self::META_EXPORTED_AT === $column_name ) {
			echo esc_html( $this->format_datetime( (string) get_post_meta( $post_id, self::META_EXPORTED_AT, true ) ) );
			return;
		}

		if ( self::META_STATUS === $column_name ) {
			$this->render_status_badge( $post_id );
			return;
		}

		if ( 'storeaccountant_progress' === $column_name ) {
			printf(
				'<span data-storeaccountant-export-id="%1$d" data-storeaccountant-export-progress>%2$s</span>',
				(int) $post_id,
				esc_html( $this->format_progress( $post_id ) )
			);
			return;
		}

		if ( self::META_FILTERS === $column_name ) {
			echo esc_html( $this->format_filters( $post_id ) );
			return;
		}

		if ( self::META_EXPORT_ADAPTER === $column_name ) {
			echo esc_html( $this->format_export_adapter( $this->get_export_adapter_id( $post_id ) ) );
			return;
		}

		if ( self::META_EXPORT_WRITER === $column_name ) {
			echo esc_html( $this->format_export_writer( $this->get_export_writer_id( $post_id ) ) );
			return;
		}

		if ( self::META_PATH === $column_name ) {
			$this->render_download_or_status( $post_id );
			return;
		}

		if ( self::META_CONFIGURATION_ID === $column_name ) {
			$this->render_configuration( $post_id );
			return;
		}

		if ( self::META_TRIGGERED_BY === $column_name ) {
			$user = get_user_by( 'id', (int) get_post_meta( $post_id, self::META_TRIGGERED_BY, true ) );
			echo esc_html( $user ? $user->display_name : __( 'Unknown', 'storeaccountant' ) );
		}
	}

	/**
	 * Renders the export configuration relation for the admin list.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function render_configuration( int $post_id ): void {
		$configuration_id = (int) get_post_meta( $post_id, self::META_CONFIGURATION_ID, true );

		if ( $configuration_id <= 0 ) {
			echo esc_html__( 'Quick Export', 'storeaccountant' );
			return;
		}

		$configuration = get_post( $configuration_id );

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type ) {
			printf(
				'<span class="storeaccountant-deleted-configuration" data-tooltip="%1$s">%2$s</span>',
				esc_attr__( 'This export configuration has been deleted.', 'storeaccountant' ),
				esc_html__( 'Deleted configuration', 'storeaccountant' )
			);
			return;
		}

		$title = get_the_title( $configuration );
		$title = is_string( $title ) && '' !== $title ? $title : __( 'Untitled configuration', 'storeaccountant' );

		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_VIEW, $configuration_id ) ) {
			echo esc_html( $title );
			return;
		}

		printf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $this->get_configuration_view_url( $configuration_id ) ),
			esc_html( $title )
		);
	}

	/**
	 * Gets the custom read URL for an export configuration.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 */
	private function get_configuration_view_url( int $configuration_id ): string {
		return add_query_arg(
			[
				'page'             => 'storeaccountant-export-configuration',
				'configuration_id' => (string) $configuration_id,
				'view'             => '1',
				'return_to'        => 'config-export',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Removes editing actions from the native list table bulk actions.
	 *
	 * @param array<string, string> $actions Bulk actions.
	 *
	 * @return array<string, string>
	 */
	public function filter_bulk_actions( array $actions ): array {
		unset( $actions['edit'] );

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_DELETE ) ) {
			unset( $actions['trash'], $actions['delete'] );
		}

		return $actions;
	}

	/**
	 * Removes editing actions from saved export row actions.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param WP_Post               $post    Current post.
	 *
	 * @return array<string, string>
	 */
	public function filter_row_actions( array $actions, WP_Post $post ): array {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}

		foreach ( array_keys( $actions ) as $action ) {
			if ( str_contains( $action, 'edit' ) || str_contains( $action, 'inline' ) ) {
				unset( $actions[ $action ] );
			}
		}

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_DELETE ) ) {
			unset( $actions['trash'], $actions['delete'] );
		}

		if ( $this->permissions->can( PermissionActionIds::EXPORT_VIEW, $post->ID ) ) {
			$actions['view'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->get_view_url( $post->ID ) ),
				esc_html__( 'View', 'storeaccountant' )
			);
		}

		if ( ExportStatus::FAILED === (string) get_post_meta( $post->ID, self::META_STATUS, true )
			&& $this->permissions->can( PermissionActionIds::EXPORT_CREATE )
		) {
			$actions['retry'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->get_retry_url( $post->ID ) ),
				esc_html__( 'Retry', 'storeaccountant' )
			);
		}

		return $actions;
	}

	/**
	 * Points native export edit links to the custom read view.
	 *
	 * @param string|null $link    Native edit link.
	 * @param int         $post_id Post ID.
	 * @param string      $context Link context.
	 */
	public function filter_edit_post_link( ?string $link, int $post_id, string $context ): ?string {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $link;
		}

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_VIEW, $post_id ) ) {
			return null;
		}

		return $this->get_view_url( $post_id );
	}

	/**
	 * Prevents editing saved export records after creation.
	 *
	 * @param array<int, string> $caps    Primitive capabilities.
	 * @param string             $cap     Requested meta capability.
	 * @param int                $user_id User ID.
	 * @param array<int, mixed>  $args    Capability arguments.
	 *
	 * @return array<int, string>
	 */
	public function filter_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}

		if ( self::POST_TYPE !== get_post_type( (int) $args[0] ) ) {
			return $caps;
		}

		$screen = get_current_screen();

		if ( $screen && 'edit-' . self::POST_TYPE === $screen->id ) {
			return [ StoreAccountantCapabilities::READ_EXPORTS ];
		}

		return [ 'do_not_allow' ];
	}

	/**
	 * Highlights StoreAccountant while rendering the hidden export read page.
	 *
	 * @param string|null $parent_file Parent file.
	 */
	public function filter_parent_file( ?string $parent_file ): string {
		if ( ! $this->is_current_read_page() ) {
			return (string) $parent_file;
		}

		return AccountingMenu::MENU_SLUG;
	}

	/**
	 * Highlights the exports submenu while rendering the hidden export read page.
	 *
	 * @param string|null $submenu_file Submenu file.
	 */
	public function filter_submenu_file( ?string $submenu_file ): string {
		if ( ! $this->is_current_read_page() ) {
			return (string) $submenu_file;
		}

		return 'edit.php?post_type=' . self::POST_TYPE;
	}

	/**
	 * Restores saved export records as published records instead of drafts.
	 *
	 * @param string $new_status      New post status.
	 * @param int    $post_id         Post ID.
	 * @param string $previous_status Previous post status.
	 */
	public function filter_untrash_post_status( string $new_status, int $post_id, string $previous_status ): string {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $new_status;
		}

		return 'publish';
	}

	/**
	 * Removes native post state labels such as Draft or Private for exports.
	 *
	 * @param array<string, string> $post_states Native post states.
	 * @param WP_Post               $post        Current post.
	 *
	 * @return array<string, string>
	 */
	public function filter_post_states( array $post_states, WP_Post $post ): array {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $post_states;
		}

		return [];
	}

	/**
	 * Renders plugin action buttons above the native export list table.
	 *
	 * @param array<string, string> $views Native list table views.
	 *
	 * @return array<string, string>
	 */
	public function render_action_buttons( array $views ): array {
		$this->header_bar->render_overview_actions();

		return $views;
	}

	/**
	 * Renders the export read view.
	 */
	public function render(): void {
		$export     = $this->get_read_export();
		$active_tab = $this->get_active_tab( $export );
		?>
		<div class="wrap">
			<h1>
		<?php
		printf(
		/* translators: %s: export title */
			esc_html__( 'Accounting Export "%s"', 'storeaccountant' ),
			esc_html( get_the_title( $export ) )
		);
		?>
			</h1>
		<?php $this->header_bar->render_detail_actions(); ?>
		<?php $this->render_tabs( $export, $active_tab ); ?>
			<div class="storeaccountant-tab-panel">
		<?php $this->render_active_tab( $export, $active_tab ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Uses the current export record for the browser title.
	 *
	 * @param string $admin_title Complete admin title.
	 * @param string $title       Static page title.
	 */
	public function filter_admin_title( string $admin_title, string $title ): string {
		if ( ! $this->is_current_read_page() ) {
			return $admin_title;
		}

		$export = $this->get_title_export();

		if ( null === $export ) {
			return $admin_title;
		}

		$page_title = sprintf(
			/* translators: %s: export title */
			__( 'Accounting Export "%s"', 'storeaccountant' ),
			get_the_title( $export )
		);

		return str_replace( $title, $page_title, $admin_title );
	}

	/**
	 * Redirects native export edit requests to the custom read view.
	 */
	public function redirect_native_edit_screen(): void {
		$post_id = Request::get_int( 'post' );
		$action  = Request::get_key( 'action' );

		if ( $post_id <= 0 || 'edit' !== $action || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		wp_safe_redirect( $this->get_view_url( $post_id ) );
		exit;
	}

	/**
	 * Prevents direct access to the native export list without StoreAccountant admin access.
	 */
	public function guard_native_list_screen(): void {
		$post_type = Request::get_key( 'post_type', 'post' );

		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_LIST ) ) {
			wp_die( esc_html__( 'You are not allowed to view accounting exports.', 'storeaccountant' ) );
		}
	}

	/**
	 * Gets and validates the export currently being viewed.
	 */
	private function get_read_export(): WP_Post {
		$export_id = Request::get_int( 'export_id' );

		if ( $export_id <= 0 ) {
			wp_die( esc_html__( 'The requested accounting export does not exist.', 'storeaccountant' ) );
		}

		$export = get_post( $export_id );

		if ( ! $export || self::POST_TYPE !== $export->post_type || ! $this->permissions->can( PermissionActionIds::EXPORT_VIEW, $export_id ) ) {
			wp_die( esc_html__( 'You are not allowed to view this accounting export.', 'storeaccountant' ) );
		}

		return $export;
	}

	/**
	 * Gets the export used for non-blocking title rendering.
	 */
	private function get_title_export(): ?WP_Post {
		$export_id = Request::get_int( 'export_id' );

		if ( $export_id <= 0 ) {
			return null;
		}

		$export = get_post( $export_id );

		if ( ! $export instanceof WP_Post || self::POST_TYPE !== $export->post_type ) {
			return null;
		}

		return $export;
	}

	/**
	 * Gets the active read-view tab.
	 *
	 * @param WP_Post $export Current export.
	 */
	private function get_active_tab( WP_Post $export ): string {
		$tabs        = $this->get_tabs( $export );
		$default_tab = (string) array_key_first( $tabs );
		$tab         = Request::get_key( 'tab', $default_tab );

		return array_key_exists( $tab, $tabs ) ? $tab : $default_tab;
	}

	/**
	 * Renders export read-view tabs.
	 *
	 * @param WP_Post $export     Current export.
	 * @param string  $active_tab Active tab identifier.
	 */
	private function render_tabs( WP_Post $export, string $active_tab ): void {
		$tabs = $this->get_tabs( $export );
		?>
		<nav class="nav-tab-wrapper storeaccountant-export-tabs" aria-label="<?php esc_attr_e( 'Export sections', 'storeaccountant' ); ?>">
		<?php foreach ( $tabs as $tab_id => $label ) : ?>
				<a
					class="nav-tab <?php echo esc_attr( $active_tab === $tab_id ? 'nav-tab-active' : '' ); ?>"
					href="<?php echo esc_url( $this->get_tab_url( $export->ID, $tab_id ) ); ?>"
				>
			<?php echo esc_html( $label ); ?>
				</a>
		<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Renders the active read-view tab.
	 *
	 * @param WP_Post $export     Current export.
	 * @param string  $active_tab Active tab identifier.
	 */
	private function render_active_tab( WP_Post $export, string $active_tab ): void {
		foreach ( $this->get_supported_tab_providers( $export ) as $provider ) {
			if ( array_key_exists( $active_tab, $provider->get_tabs( $export ) ) ) {
				$provider->render( $active_tab, $export );
				return;
			}
		}

		foreach ( $this->get_supported_tab_providers( $export ) as $provider ) {
			$tabs = $provider->get_tabs( $export );

			if ( [] === $tabs ) {
				continue;
			}

			$provider->render( (string) array_key_first( $tabs ), $export );
			return;
		}
	}

	/**
	 * Gets all available read-view tabs.
	 *
	 * @param WP_Post $export Current export.
	 *
	 * @return array<string, string>
	 */
	private function get_tabs( WP_Post $export ): array {
		$tabs = [];

		foreach ( $this->get_supported_tab_providers( $export ) as $provider ) {
			$tabs = array_merge( $tabs, $provider->get_tabs( $export ) );
		}

		return $tabs;
	}

	/**
	 * Gets tab providers that support the current export.
	 *
	 * @param WP_Post $export Current export.
	 *
	 * @return array<int, \StoreAccountant\Export\Contract\ExportReadTabProviderInterface>
	 */
	private function get_supported_tab_providers( WP_Post $export ): array {
		$providers = [];

		foreach ( $this->read_tab_providers->get_all() as $provider ) {
			if ( $provider->supports( $export ) ) {
				$providers[] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * Renders export submission notices on the native post type list table.
	 */
	public function render_export_notice(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id ) {
			return;
		}

		if ( Request::has_get( 'storeaccountant_export_created' ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'The accounting export was saved and queued.', 'storeaccountant' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( ! Request::has_get( 'storeaccountant_export_error' ) ) {
			return;
		}

		$notice  = Request::get_key( 'storeaccountant_export_error' );
		$message = __( 'The accounting export could not be saved.', 'storeaccountant' );

		if ( 'duplicate_title' === $notice ) {
			$message = __( 'An accounting export with this title already exists. Choose a unique export title.', 'storeaccountant' );
		}

		if ( 'missing_title' === $notice ) {
			$message = __( 'Enter an export title before starting the export.', 'storeaccountant' );
		}

		if ( 'title_contains_password' === $notice ) {
			$message = __( 'The export title must not contain the download password.', 'storeaccountant' );
		}
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<?php $this->render_diagnostic_notice(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the diagnostic package hint when an incident was logged.
	 */
	private function render_diagnostic_notice(): void {
		$support_id = Request::get_key( 'storeaccountant_diagnostic_support_id' );

		if ( '' === $support_id || ! $this->permissions->can( PermissionActionIds::DIAGNOSTIC_PACKAGE_DOWNLOAD ) ) {
			return;
		}
		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: diagnostic support ID */
					__( 'StoreAccountant logged this error with support ID %s.', 'storeaccountant' ),
					$support_id
				)
			);
			?>
			<a href="<?php echo esc_url( $this->get_diagnostic_download_url( $support_id ) ); ?>">
				<?php esc_html_e( 'Download diagnostic package', 'storeaccountant' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Streams a generated export file to the current administrator.
	 */
	public function handle_download_export_file(): void {
		$post_id = Request::get_int( 'export_id' );

		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'The requested accounting export does not exist.', 'storeaccountant' ) );
		}

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_DOWNLOAD, $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to download accounting exports.', 'storeaccountant' ) );
		}

		check_admin_referer( $this->get_download_nonce_action( $post_id ) );

		$download_url = $this->download_urls->get_url( $post_id );

		if ( '' === $download_url ) {
			wp_die( esc_html__( 'The requested export file is unavailable.', 'storeaccountant' ) );
		}

		wp_safe_redirect( $download_url );
		exit;
	}

	/**
	 * Retries a failed export.
	 */
	public function handle_retry_export(): void {
		$post_id = Request::get_int( 'export_id' );

		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'The requested accounting export does not exist.', 'storeaccountant' ) );
		}

		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_CREATE ) ) {
			wp_die( esc_html__( 'You are not allowed to start accounting exports.', 'storeaccountant' ) );
		}

		check_admin_referer( $this->get_retry_nonce_action( $post_id ) );

		$this->repository->reset_for_retry( $post_id );

		try {
			$this->repository->mark_queued( $post_id );
			$this->message_bus->dispatch( new StartExportMessage( $post_id, $this->get_export_writer_id( $post_id ) ) );
				ExportEventDispatcher::dispatch(
					ExportEvents::QUEUED,
					$post_id,
					[
						'action'    => 'storeaccountant_retry_export',
						'export_id' => $post_id,
						'retry'     => true,
					]
				);
			$this->loopback_dispatcher->maybe_dispatch_for_manual_export( $post_id );
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$post_id,
				__( 'The accounting export could not be queued.', 'storeaccountant' ),
				$exception,
				[
					'action'      => 'storeaccountant_retry_export',
					'export_id'   => $post_id,
					'log_message' => 'The accounting export could not be queued.',
				]
			);
			$incident = $this->diagnostics->error(
				'retry_export',
				__( 'The accounting export could not be saved.', 'storeaccountant' ),
				[
					'reason'    => 'retry_export_queue_failed',
					'export_id' => $post_id,
				],
				null,
				$exception
			);
			$this->redirect_list_with_error( '1', $incident );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'                      => self::POST_TYPE,
					'storeaccountant_export_created' => (string) $post_id,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Redirects back to the export list with an error notice.
	 */
	private function redirect_list_with_error( string $error = '1', ?DiagnosticIncident $incident = null ): void {
		$args = [
			'post_type'                    => self::POST_TYPE,
			'storeaccountant_export_error' => $error,
		];

		if ( null !== $incident ) {
			$args['storeaccountant_diagnostic_support_id'] = $incident->support_id;
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Gets the authorized diagnostic package download URL.
	 */
	private function get_diagnostic_download_url( string $support_id ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'     => DiagnosticIncidentDownloadController::ACTION,
					'support_id' => $support_id,
				],
				admin_url( 'admin-post.php' )
			),
			DiagnosticIncidentDownloadController::ACTION
		);
	}

	/**
	 * Formats stored filters.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function format_filters( int $post_id ): string {
		$filters = $this->filter_serializer->decode( (string) get_post_meta( $post_id, self::META_FILTERS, true ) );

		if ( [] === $filters ) {
			return __( 'None', 'storeaccountant' );
		}

		return sprintf(
		/* translators: %d: number of filters */
			_n( '%d filter', '%d filters', count( $filters ), 'storeaccountant' ),
			count( $filters )
		);
	}
	/**
	 * Formats a stored datetime using the WordPress date and time format.
	 *
	 * @param string $datetime Date and time in MySQL format.
	 */
	private function format_datetime( string $datetime ): string {
		return AdminDateFormatter::format_mysql_datetime( $datetime );
	}

	/**
	 * Gets the export adapter identifier.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_export_adapter_id( int $post_id ): string {
		$adapter_id = (string) get_post_meta( $post_id, self::META_EXPORT_ADAPTER, true );

		if ( '' !== $adapter_id && null !== $this->adapter_registry->get( $adapter_id ) ) {
			return $adapter_id;
		}

		return $adapter_id;
	}

	/**
	 * Gets the export writer identifier.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_export_writer_id( int $post_id ): string {
		$writer_id = (string) get_post_meta( $post_id, self::META_EXPORT_WRITER, true );

		if ( '' !== $writer_id ) {
			return $writer_id;
		}

		return CsvExportRenderer::RENDERER_ID;
	}

	/**
	 * Formats an export adapter identifier.
	 *
	 * @param string $export_adapter Export adapter identifier.
	 */
	private function format_export_adapter( string $export_adapter ): string {
		if ( '' === $export_adapter ) {
			$export_adapter = CsvExportRenderer::RENDERER_ID;
		}

		$adapter = $this->adapter_registry->get( $export_adapter );

		if ( null !== $adapter ) {
			return I18n::translate_registry_label( 'export_adapter_', $adapter->get_id() );
		}

		return I18n::translate_registry_label( 'export_adapter_', $export_adapter );
	}

	/**
	 * Formats an export writer identifier.
	 *
	 * @param string $export_writer Export writer identifier.
	 */
	private function format_export_writer( string $export_writer ): string {
		if ( '' === $export_writer ) {
			$export_writer = CsvExportRenderer::RENDERER_ID;
		}

		$writer = $this->writer_registry->get( $export_writer );

		if ( null !== $writer ) {
			return I18n::translate_registry_label( 'exporter_', $writer->get_id() );
		}

		return I18n::translate_registry_label( 'exporter_', $export_writer );
	}

	/**
	 * Gets the lifecycle status used for admin display.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_effective_status( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, self::META_STATUS, true );

		if ( ExportStatus::is_valid( $status ) ) {
			return $status;
		}

		return '' !== $this->normalize_storage_path( (string) get_post_meta( $post_id, self::META_PATH, true ) )
		? ExportStatus::COMPLETED
		: ExportStatus::SCHEDULED;
	}

	/**
	 * Formats export progress.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function format_progress( int $post_id ): string {
		$total_batches     = (int) get_post_meta( $post_id, self::META_TOTAL_BATCHES, true );
		$processed_batches = (int) get_post_meta( $post_id, self::META_PROCESSED_BATCHES, true );
		$total_items       = (int) get_post_meta( $post_id, self::META_TOTAL_ITEMS, true );
		$processed_items   = (int) get_post_meta( $post_id, self::META_PROCESSED_ITEMS, true );

		if ( $total_batches <= 0 ) {
			return '';
		}

		return sprintf(
		/* translators: %1$d: processed batches, %2$d: total batches, %3$d: processed items, %4$d: total items */
			__( '%1$d / %2$d batches, %3$d / %4$d items', 'storeaccountant' ),
			$processed_batches,
			$total_batches,
			$processed_items,
			$total_items
		);
	}

	/**
	 * Renders the download button for a generated export file.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function render_download_button( int $post_id ): void {
		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_DOWNLOAD, $post_id ) ) {
			return;
		}

		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $post_id, self::META_PATH, true ) );
		$status       = (string) get_post_meta( $post_id, self::META_STATUS, true );

		if ( ExportStatus::FAILED === $status ) {
			$this->render_disabled_download_button( __( 'The export failed before a file could be generated.', 'storeaccountant' ) );
			return;
		}

		if ( '' === $storage_path ) {
			$this->render_disabled_download_button( __( 'No export file has been generated yet.', 'storeaccountant' ) );
			return;
		}

		$storage_adapter = $this->get_storage_adapter( $post_id );

		if ( null === $storage_adapter ) {
			$this->render_disabled_download_button( __( 'The storage adapter for this export is unavailable.', 'storeaccountant' ) );
			return;
		}

		if ( ! $storage_adapter->file_exists( $storage_path ) ) {
			$this->render_disabled_download_button( __( 'The export file no longer exists in storage.', 'storeaccountant' ) );
			return;
		}

		printf(
			'<a class="button button-small" href="%1$s" title="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a>',
			esc_url( $this->download_urls->get_url( $post_id ) ),
			esc_attr( $this->storage_path_generator->get_display_path( $storage_adapter->get_id(), $storage_path ) ),
			esc_html__( 'Download', 'storeaccountant' )
		);
	}

	/**
	 * Renders a download button when available, otherwise the current status.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function render_download_or_status( int $post_id ): void {
		printf(
			'<span data-storeaccountant-export-id="%1$d" data-storeaccountant-export-actions data-storeaccountant-export-status="%2$s" data-storeaccountant-pollable="%3$s">',
			(int) $post_id,
			esc_attr( $this->get_effective_status( $post_id ) ),
			esc_attr( $this->polling_response_factory->is_export_pollable( $post_id ) ? '1' : '0' )
		);

		if ( $this->is_download_available( $post_id ) ) {
			$this->render_download_button( $post_id );
			echo '</span>';
			return;
		}

		$this->render_status_badge( $post_id );
		echo '</span>';
	}

	/**
	 * Checks whether the stored export file can be downloaded.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function is_download_available( int $post_id ): bool {
		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_DOWNLOAD, $post_id ) ) {
			return false;
		}

		if ( ExportStatus::COMPLETED !== $this->get_effective_status( $post_id ) ) {
			return false;
		}

		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $post_id, self::META_PATH, true ) );

		if ( '' === $storage_path ) {
			return false;
		}

		$storage_adapter = $this->get_storage_adapter( $post_id );

		return null !== $storage_adapter && $storage_adapter->file_exists( $storage_path );
	}

	/**
	 * Renders a colored lifecycle status badge.
	 *
	 * @param int  $post_id       Export post ID.
	 * @param bool $show_progress Whether to include progress below the status.
	 */
	private function render_status_badge( int $post_id, bool $show_progress = false ): void {
		$status = $this->get_effective_status( $post_id );

		printf(
			'<span class="storeaccountant-export-status storeaccountant-export-status--%1$s">%2$s</span>',
			esc_attr( sanitize_html_class( $status ) ),
			esc_html( ExportStatus::get_label( $status ) )
		);

		if ( $show_progress && ExportStatus::COMPLETED !== $status ) {
			$progress = $this->format_progress( $post_id );

			if ( '' !== $progress ) {
				printf(
					'<span class="storeaccountant-export-status-progress">%s</span>',
					esc_html( $progress )
				);
			}
		}
	}

	/**
	 * Renders a disabled download button with a tooltip.
	 *
	 * @param string $message Tooltip message.
	 */
	private function render_disabled_download_button( string $message ): void {
		printf(
			'<span class="storeaccountant-disabled-download" title="%1$s"><button type="button" class="button button-small" disabled="disabled">%2$s</button></span>',
			esc_attr( $message ),
			esc_html__( 'Download', 'storeaccountant' )
		);
	}

	/**
	 * Gets the storage adapter for an export.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_storage_adapter( int $post_id ): ?StorageAdapterInterface {
		$storage_engine = (string) get_post_meta( $post_id, self::META_STORAGE_ENGINE, true );

		if ( '' === $storage_engine ) {
			return null;
		}

		return $this->storage_adapters->get( sanitize_key( $storage_engine ) );
	}

	/**
	 * Gets the retry URL for an export.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_retry_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				[
					'action'    => 'storeaccountant_retry_export',
					'export_id' => (string) $post_id,
				],
				admin_url( 'admin-post.php' )
			),
			$this->get_retry_nonce_action( $post_id )
		);
	}

	/**
	 * Gets the nonce action for export retry.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_retry_nonce_action( int $post_id ): string {
		return 'storeaccountant_retry_export_' . $post_id;
	}

	/**
	 * Gets the nonce action for an export download.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_download_nonce_action( int $post_id ): string {
		return 'storeaccountant_download_export_' . $post_id;
	}

	/**
	 * Gets the custom read URL for an export.
	 *
	 * @param int $post_id Export post ID.
	 */
	private function get_view_url( int $post_id ): string {
		return add_query_arg(
			[
				'page'      => 'storeaccountant-export',
				'export_id' => (string) $post_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Gets a tab URL for the export read view.
	 *
	 * @param int    $post_id Export post ID.
	 * @param string $tab     Tab identifier.
	 */
	private function get_tab_url( int $post_id, string $tab ): string {
		$args = [
			'page'      => 'storeaccountant-export',
			'export_id' => (string) $post_id,
		];

		if ( 'export-details' !== $tab ) {
			$args['tab'] = $tab;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Checks whether the current admin page is the export read page.
	 */
	private function is_current_read_page(): bool {
		return 'storeaccountant-export' === Request::get_key( 'page' );
	}

	/**
	 * Normalizes stored storage paths.
	 *
	 * @param string $storage_path Stored storage path.
	 */
	private function normalize_storage_path( string $storage_path ): string {
		$separator_position = strrpos( $storage_path, '#' );

		if ( false === $separator_position ) {
			return $storage_path;
		}

		$archive_path = $this->normalize_storage_archive_path( substr( $storage_path, 0, $separator_position ) );

		if ( '' === $archive_path ) {
			return ltrim( substr( $storage_path, $separator_position + 1 ), '/' );
		}

		return $archive_path;
	}

	/**
	 * Normalizes the archive side of a storage path.
	 *
	 * @param string $archive_path Stored archive path.
	 */
	private function normalize_storage_archive_path( string $archive_path ): string {
		$archive_path = str_replace( '\\', '/', $archive_path );
		$marker       = '/storeaccountant/';
		$marker_pos   = strpos( $archive_path, $marker );

		if ( false !== $marker_pos ) {
			$archive_path = substr( $archive_path, $marker_pos + strlen( $marker ) );
		}

		$segments = array_filter(
			explode( '/', $archive_path ),
			static fn ( string $segment ): bool => '' !== $segment && '.' !== $segment && '..' !== $segment
		);
		$segments = array_map( 'sanitize_file_name', $segments );
		$segments = array_filter(
			$segments,
			static fn ( string $segment ): bool => '' !== $segment
		);

		return implode( '/', $segments );
	}
}
