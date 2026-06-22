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

namespace StoreAccountant\Export\Configuration;

use WP_Post;
use StoreAccountant\Admin\AdminDateFormatter;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\I18n;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function array_keys;
use function sprintf;
use function str_contains;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers reusable export configuration records.
 */
final readonly class ExportConfigurationPostType implements HookRegistrarInterface {
	public const POST_TYPE = 'storeacct_config';

	public const META_FILTERS                  = '_storeaccountant_config_filters';
	public const META_EXPORT_ADAPTER           = '_storeaccountant_config_export_adapter';
	public const META_EXPORT_WRITER            = '_storeaccountant_config_export_writer';
	public const META_STORAGE_ENGINE           = '_storeaccountant_config_storage_engine';
	public const META_BATCH_SIZE               = '_storeaccountant_config_batch_size';
	public const META_ORDER_TAX_FIELD_PROVIDER = '_storeaccountant_config_order_tax_field_provider';
	public const META_ADDITIONAL_SETTINGS      = '_storeaccountant_config_additional_settings';
	public const META_FIELD_MAPPING            = '_storeaccountant_config_field_mapping';
	public const META_DOWNLOAD_PASSWORD        = '_storeaccountant_config_download_password';
	public const META_DOWNLOAD_PASSWORD_HASH   = '_storeaccountant_config_download_password_hash';

	private const COLUMN_CREATED_AT = 'storeaccountant_created_at';
	private const COLUMN_CREATED_BY = 'storeaccountant_created_by';

	/**
	 * Initializes the export configuration post type.
	 *
	 * @param AccountingHeaderBar    $header_bar               Accounting header bar.
	 * @param ExportAdapterRegistry  $export_adapters          Export adapter registry.
	 * @param ExportRendererRegistry $export_writers           Export writer registry.
	 * @param StorageAdapterRegistry $storage_adapters         Storage adapter registry.
	 * @param PermissionChecker      $permissions              Permission checker.
	 */
	public function __construct(
		private AccountingHeaderBar $header_bar,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $export_writers,
		private StorageAdapterRegistry $storage_adapters,
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'admin_notices', [ $this, 'render_created_notice' ] );
		add_filter( 'views_edit-' . self::POST_TYPE, [ $this, 'render_action_buttons' ] );
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ] );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', [ $this, 'filter_columns' ] );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, [ $this, 'filter_bulk_actions' ] );
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 10, 2 );
		add_filter( 'get_edit_post_link', [ $this, 'filter_edit_post_link' ], 10, 3 );
		add_filter( 'wp_untrash_post_status', [ $this, 'filter_untrash_post_status' ], 10, 3 );
		add_filter( 'display_post_states', [ $this, 'filter_post_states' ], 10, 2 );
		add_action( 'load-post.php', [ $this, 'redirect_native_edit_screen' ] );
		add_action( 'load-edit.php', [ $this, 'guard_native_list_screen' ] );
	}

	/**
	 * Registers the export configuration post type.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'          => [
					'name'          => __( 'Export Configurations', 'storeaccountant' ),
					'singular_name' => __( 'Export Configuration', 'storeaccountant' ),
					'menu_name'     => __( 'Export Configurations', 'storeaccountant' ),
					'add_new_item'  => __( 'Add New Export Configuration', 'storeaccountant' ),
					'edit_item'     => __( 'Edit Export Configuration', 'storeaccountant' ),
					'view_item'     => __( 'View Export Configuration', 'storeaccountant' ),
					'search_items'  => __( 'Search Export Configurations', 'storeaccountant' ),
					'not_found'     => __( 'No export configurations found.', 'storeaccountant' ),
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'capability_type' => 'post',
				'capabilities'    => [
					'create_posts'           => 'do_not_allow',
					'delete_others_posts'    => StoreAccountantCapabilities::DELETE_CONFIGURATIONS,
					'delete_posts'           => StoreAccountantCapabilities::DELETE_CONFIGURATIONS,
					'delete_private_posts'   => StoreAccountantCapabilities::DELETE_CONFIGURATIONS,
					'delete_published_posts' => StoreAccountantCapabilities::DELETE_CONFIGURATIONS,
					'edit_others_posts'      => StoreAccountantCapabilities::EDIT_CONFIGURATION,
					'edit_posts'             => StoreAccountantCapabilities::READ_CONFIGURATIONS,
					'edit_private_posts'     => StoreAccountantCapabilities::EDIT_CONFIGURATION,
					'edit_published_posts'   => StoreAccountantCapabilities::EDIT_CONFIGURATION,
					'publish_posts'          => StoreAccountantCapabilities::CREATE_CONFIGURATIONS,
					'read_private_posts'     => StoreAccountantCapabilities::READ_CONFIGURATIONS,
				],
				'map_meta_cap'    => true,
				'supports'        => [ 'title' ],
				'menu_icon'       => 'dashicons-admin-generic',
			]
		);
	}

	/**
	 * Replaces admin list columns for saved export configurations.
	 *
	 * @param array<string, string> $columns Registered columns.
	 *
	 * @return array<string, string>
	 */
	public function filter_columns( array $columns ): array {
		return [
			'cb'                      => $columns['cb'] ?? '<input type="checkbox" />',
			'title'                   => __( 'Title', 'storeaccountant' ),
			self::META_EXPORT_ADAPTER => __( 'Export Type', 'storeaccountant' ),
			self::META_EXPORT_WRITER  => __( 'Export Format', 'storeaccountant' ),
			self::META_STORAGE_ENGINE => __( 'Storage Location', 'storeaccountant' ),
			self::COLUMN_CREATED_AT   => __( 'Created At', 'storeaccountant' ),
			self::COLUMN_CREATED_BY   => __( 'Created By', 'storeaccountant' ),
		];
	}

	/**
	 * Renders custom admin list column values.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Configuration post ID.
	 */
	public function render_column( string $column_name, int $post_id ): void {
		if ( self::META_EXPORT_ADAPTER === $column_name ) {
			echo esc_html( $this->format_export_adapter( $this->get_export_adapter_id( $post_id ) ) );
			return;
		}

		if ( self::META_EXPORT_WRITER === $column_name ) {
			echo esc_html( $this->format_export_writer( $this->get_export_writer_id( $post_id ) ) );
			return;
		}

		if ( self::META_STORAGE_ENGINE === $column_name ) {
			echo esc_html( $this->format_storage_adapter( (string) get_post_meta( $post_id, self::META_STORAGE_ENGINE, true ) ) );
			return;
		}

		if ( self::COLUMN_CREATED_AT === $column_name ) {
			echo esc_html( $this->format_created_at( $post_id ) );
			return;
		}

		if ( self::COLUMN_CREATED_BY === $column_name ) {
			echo esc_html( $this->format_created_by( $post_id ) );
		}
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

		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_DELETE ) ) {
			unset( $actions['trash'], $actions['delete'] );
		}

		return $actions;
	}

	/**
	 * Removes editing actions from saved export configuration row actions.
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
			if ( str_contains( $action, 'inline' ) ) {
				unset( $actions[ $action ] );
			}
		}

		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_DELETE ) ) {
			unset( $actions['trash'], $actions['delete'] );
		}

		if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT, $post->ID ) ) {
			$actions['edit'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->get_edit_url( $post->ID ) ),
				esc_html__( 'Edit', 'storeaccountant' )
			);
		}

		if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_VIEW, $post->ID ) ) {
			$actions['view'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $this->get_view_url( $post->ID ) ),
				esc_html__( 'View', 'storeaccountant' )
			);
		}

		return $actions;
	}

	/**
	 * Points native configuration title links to the custom read view.
	 *
	 * @param string|null $link    Edit link.
	 * @param int         $post_id Post ID.
	 * @param string      $context Link context.
	 */
	public function filter_edit_post_link( ?string $link, int $post_id, string $context ): ?string {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return $link;
		}

		if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_VIEW, $post_id ) ) {
			return $this->get_view_url( $post_id );
		}

		if ( $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT, $post_id ) ) {
			return $this->get_edit_url( $post_id );
		}

		return null;
	}

	/**
	 * Restores export configurations as published records instead of drafts.
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
	 * Removes native post state labels such as Draft or Private for configurations.
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
	 * Renders plugin action buttons above the native configuration list table.
	 *
	 * @param array<string, string> $views Native list table views.
	 *
	 * @return array<string, string>
	 */
	public function render_action_buttons( array $views ): array {
		$this->header_bar->render_configuration_actions();

		return $views;
	}

	/**
	 * Highlights StoreAccountant while rendering the hidden configuration list.
	 *
	 * @param string|null $parent_file Parent file.
	 */
	public function filter_parent_file( ?string $parent_file ): string {
		if ( ! $this->is_current_list_screen() ) {
			return (string) $parent_file;
		}

		return AccountingMenu::MENU_SLUG;
	}

	/**
	 * Highlights the exports submenu while rendering the hidden configuration list.
	 *
	 * @param string|null $submenu_file Submenu file.
	 */
	public function filter_submenu_file( ?string $submenu_file ): string {
		if ( ! $this->is_current_list_screen() ) {
			return (string) $submenu_file;
		}

		return 'edit.php?post_type=' . ExportPostType::POST_TYPE;
	}

	/**
	 * Renders the export configuration created notice.
	 */
	public function render_created_notice(): void {
		$screen = get_current_screen();

		if (
				! $screen
				|| 'edit-' . self::POST_TYPE !== $screen->id
				|| ( ! Request::has_get( 'storeaccountant_export_configuration_saved' ) && ! Request::has_get( 'storeaccountant_export_configuration_created' ) )
			) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'The export configuration was saved.', 'storeaccountant' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirects the native post edit screen to the custom configuration read view.
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
	 * Prevents direct access to the native configuration list without StoreAccountant admin access.
	 */
	public function guard_native_list_screen(): void {
		$post_type = Request::get_key( 'post_type', 'post' );

		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_LIST ) ) {
			wp_die( esc_html__( 'You are not allowed to view export configurations.', 'storeaccountant' ) );
		}
	}

	/**
	 * Gets the export adapter identifier.
	 *
	 * @param int $post_id Export configuration post ID.
	 */
	private function get_export_adapter_id( int $post_id ): string {
		$adapter_id = (string) get_post_meta( $post_id, self::META_EXPORT_ADAPTER, true );

		if ( '' !== $adapter_id && null !== $this->export_adapters->get( $adapter_id ) ) {
			return $adapter_id;
		}

		return $adapter_id;
	}

	/**
	 * Gets the export writer identifier.
	 *
	 * @param int $post_id Export configuration post ID.
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

		$adapter = $this->export_adapters->get( $export_adapter );

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

		$writer = $this->export_writers->get( $export_writer );

		if ( null !== $writer ) {
			return I18n::translate_registry_label( 'exporter_', $writer->get_id() );
		}

		return I18n::translate_registry_label( 'exporter_', $export_writer );
	}

	/**
	 * Formats a storage adapter identifier.
	 *
	 * @param string $storage_adapter Storage adapter identifier.
	 */
	private function format_storage_adapter( string $storage_adapter ): string {
		if ( '' === $storage_adapter ) {
			return '';
		}

		$adapter = $this->storage_adapters->get( $storage_adapter );

		if ( null !== $adapter ) {
			return I18n::translate_registry_label( 'storage_adapter_', $adapter->get_id() );
		}

		return I18n::translate_registry_label( 'storage_adapter_', $storage_adapter );
	}

	/**
	 * Checks whether the current screen is the export configuration list.
	 */
	private function is_current_list_screen(): bool {
		global $typenow;

		if ( self::POST_TYPE === $typenow ) {
			return true;
		}

		return self::POST_TYPE === Request::get_key( 'post_type' );
	}

	/**
	 * Gets the custom edit URL for a configuration.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_edit_url( int $post_id ): string {
		return add_query_arg(
			[
				'page'             => 'storeaccountant-export-configuration',
				'configuration_id' => (string) $post_id,
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Gets the custom read URL for a configuration.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_view_url( int $post_id ): string {
		return add_query_arg(
			[
				'page'             => 'storeaccountant-export-configuration',
				'configuration_id' => (string) $post_id,
				'view'             => '1',
			],
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Formats the configuration creation date.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function format_created_at( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		return get_the_time(
			AdminDateFormatter::get_datetime_format(),
			$post
		);
	}

	/**
	 * Formats the configuration author.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function format_created_by( int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return '';
		}

		$user = get_user_by( 'id', (int) $post->post_author );

		return $user ? $user->display_name : __( 'Unknown', 'storeaccountant' );
	}
}
