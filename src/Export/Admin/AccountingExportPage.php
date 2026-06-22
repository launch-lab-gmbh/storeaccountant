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

namespace StoreAccountant\Export\Admin;

use Throwable;
use WP_Error;
use Symfony\Component\Messenger\MessageBusInterface;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Diagnostic\Admin\DiagnosticIncidentDownloadController;
use StoreAccountant\Diagnostic\DiagnosticIncident;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function is_array;
use function sprintf;
use function str_starts_with;
use function str_contains;
use function substr;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the accounting export page.
 */
final readonly class AccountingExportPage implements HookRegistrarInterface {
	public const PAGE_SLUG = 'storeaccountant-export-create';

	private const QUICK_EXPORT_DRAFT_META_KEY = '_storeaccountant_quick_export_draft';

	private const QUICK_EXPORT_DETAILS_STEP = 'details';

	/**
	 * Initializes the page.
	 *
	 * @param AccountingExportPageForm          $form                   Export form renderer.
	 * @param ExportRepository                  $repository             Export repository.
	 * @param MessageBusInterface               $message_bus            Message bus.
	 * @param StorageAdapterRegistry            $storage_adapters        storage adapter registry.
	 * @param ExportAdapterRegistry             $export_adapters        Export adapter registry.
	 * @param ExportRendererRegistry            $export_writers         Export writer registry.
	 * @param ExportFilterFieldProviderRegistry $filter_field_providers Export filter field providers.
	 * @param ExportSettingsFields              $settings_fields        Shared export settings fields.
	 * @param ExportFilterSelectionSerializer   $filter_serializer      Filter selection serializer.
	 * @param ExportFilterSnapshotter           $filter_snapshotter     Filter snapshotter.
	 * @param AccountingHeaderBar               $header_bar             Accounting header bar.
	 * @param PermissionChecker                 $permissions            Permission checker.
	 * @param QueueLoopbackDispatcher           $loopback_dispatcher    Queue loopback dispatcher.
	 * @param DownloadPasswordManager           $passwords              Download password manager.
	 * @param DiagnosticIncidentLogger          $diagnostics            Diagnostic incident logger.
	 */
	public function __construct(
		private AccountingExportPageForm $form,
		private ExportRepository $repository,
		private MessageBusInterface $message_bus,
		private StorageAdapterRegistry $storage_adapters,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $export_writers,
		private ExportFilterFieldProviderRegistry $filter_field_providers,
		private ExportSettingsFields $settings_fields,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportFilterSnapshotter $filter_snapshotter,
		private AccountingHeaderBar $header_bar,
		private PermissionChecker $permissions,
		private QueueLoopbackDispatcher $loopback_dispatcher,
		private DownloadPasswordManager $passwords,
		private DiagnosticIncidentLogger $diagnostics
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_head', [ $this, 'remove_hidden_submenu_page' ] );
		add_action( 'admin_post_storeaccountant_start_export', [ $this, 'handle_start_export' ] );
		add_action( 'admin_post_storeaccountant_start_export_from_overview', [ $this, 'handle_start_export_from_overview' ] );
		add_filter( 'parent_file', [ $this, 'filter_parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'filter_submenu_file' ] );
	}

	/**
	 * Adds hidden plugin pages used by the export list action buttons.
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			AccountingMenu::MENU_SLUG,
			__( 'Create New Export', 'storeaccountant' ),
			__( 'Create New Export', 'storeaccountant' ),
			$this->permissions->get_capability( PermissionActionIds::EXPORT_CREATE, StoreAccountantCapabilities::CREATE_EXPORTS ),
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);
	}

	/**
	 * Removes hidden plugin pages from the visible accounting submenu after access checks.
	 */
	public function remove_hidden_submenu_page(): void {
		remove_submenu_page( AccountingMenu::MENU_SLUG, self::PAGE_SLUG );
	}

	/**
	 * Renders the initial accounting export admin page.
	 */
	public function render(): void {
		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_CREATE ) ) {
			wp_die( esc_html__( 'You are not allowed to start accounting exports.', 'storeaccountant' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->get_page_title() ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php $this->header_bar->render_detail_actions(); ?>

			<?php $this->form->render( $this->get_quick_export_draft_for_render() ); ?>
		</div>
		<?php
	}

	/**
	 * Handles the export form submission.
	 */
	public function handle_start_export(): void {
		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_CREATE ) ) {
			wp_die( esc_html__( 'You are not allowed to start accounting exports.', 'storeaccountant' ) );
		}

		check_admin_referer( 'storeaccountant_start_export', 'storeaccountant_export_nonce' );

		$is_quick_export_prepare = '1' === Request::post_key( 'storeaccountant_quick_export_prepare' );
		$is_quick_export         = '1' === Request::post_key( 'storeaccountant_quick_export' );

		if ( $is_quick_export_prepare ) {
			$this->handle_quick_export_prepare();
		}

		if ( $is_quick_export ) {
			$this->handle_quick_export();
		}

		$this->redirect_with_error( '1', 'non_quick_export_submission' );
	}

	/**
	 * Handles the first quick export step.
	 */
	private function handle_quick_export_prepare(): void {
		$title          = trim( Request::post_text( 'storeaccountant_export_title' ) );
		$export_adapter = Request::post_key( 'storeaccountant_export_adapter', OrderExportAdapter::ADAPTER_ID );

		$this->validate_export_title(
			$title,
			fn ( string $error, string $reason ): never => $this->redirect_with_error( $error, $reason )
		);

		if ( null === $this->export_adapters->get( $export_adapter ) ) {
			$this->redirect_with_error( '1', 'quick_export_adapter_missing', null, [ 'export_adapter' => $export_adapter ] );
		}

		$this->save_quick_export_draft( $title, $export_adapter );

		$this->redirect_to_quick_export_details();
	}

	/**
	 * Handles export creation requests from the export overview selector.
	 */
	public function handle_start_export_from_overview(): void {
		if ( ! $this->permissions->can( PermissionActionIds::EXPORT_CREATE ) ) {
			wp_die( esc_html__( 'You are not allowed to start accounting exports.', 'storeaccountant' ) );
		}

		check_admin_referer( 'storeaccountant_start_export_from_overview', 'storeaccountant_export_overview_nonce' );

		$selection = Request::post_text( 'storeaccountant_export_create_selection', 'quick' );

		if ( 'quick' === $selection ) {
				wp_safe_redirect(
					add_query_arg(
						[
							'page' => self::PAGE_SLUG,
						],
						admin_url( 'admin.php' )
					)
				);
			exit;
		}

		if ( ! str_starts_with( $selection, 'configuration:' ) ) {
			$this->redirect_overview_with_error( '1', 'invalid_overview_selection' );
		}

		$configuration_id = absint( substr( $selection, 14 ) );
		$title            = trim( Request::post_text( 'storeaccountant_export_title' ) );

		$this->validate_export_title(
			$title,
			fn ( string $error, string $reason ): never => $this->redirect_overview_with_error( $error, $reason )
		);

		$password = $this->passwords->reveal_configuration_password( $configuration_id );

		if ( is_wp_error( $password ) ) {
			$this->redirect_overview_with_error( '1', 'configuration_password_reveal_failed', $password, [ 'configuration_id' => $configuration_id ] );
		}

		if ( str_contains( $title, $password ) ) {
			$this->redirect_overview_with_error( 'title_contains_password', 'title_contains_password', null, [ 'configuration_id' => $configuration_id ] );
		}

		$this->handle_configuration_export( $configuration_id, $title );
	}

	/**
	 * Creates and runs an export from a saved configuration.
	 *
	 * @param int    $configuration_id Export configuration post ID.
	 * @param string $title            Export title.
	 */
	private function handle_configuration_export( int $configuration_id, string $title ): void {
		$configuration = get_post( $configuration_id );

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type || 'publish' !== $configuration->post_status ) {
			$this->redirect_overview_with_error( '1', 'invalid_or_unpublished_configuration', null, [ 'configuration_id' => $configuration_id ] );
		}

		$filters        = $this->filter_serializer->decode( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_FILTERS, true ) );
		$storage_engine = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_STORAGE_ENGINE, true );
		$export_adapter = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_EXPORT_ADAPTER, true );
		$export_writer  = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_EXPORT_WRITER, true );
		$batch_size     = $this->get_batch_size_from_configuration( $configuration_id );

		if ( '' === $export_adapter ) {
			$export_adapter = OrderExportAdapter::ADAPTER_ID;
		}

		$filters = $this->filter_snapshotter->snapshot( $filters );

		if ( is_wp_error( $filters ) || ! $this->storage_adapters->is_enabled( $storage_engine ) ) {
			$this->redirect_overview_with_error(
				'1',
				is_wp_error( $filters ) ? 'configuration_filters_invalid' : 'configuration_storage_adapter_not_enabled',
				is_wp_error( $filters ) ? $filters : null,
				[
					'configuration_id' => $configuration_id,
					'storage_engine'   => $storage_engine,
				]
			);
		}

		$export_adapter_instance = $this->export_adapters->get( $export_adapter );
		$export_writer_instance  = $this->export_writers->get( $export_writer );

		if ( null === $export_adapter_instance || null === $export_writer_instance ) {
			$this->redirect_overview_with_error(
				'1',
				'configuration_export_adapter_or_writer_missing',
				null,
				[
					'configuration_id' => $configuration_id,
					'export_adapter'   => $export_adapter,
					'export_writer'    => $export_writer,
				]
			);
		}

		$post_id = $this->repository->create(
			$title,
			$filters,
			$storage_engine,
			$export_adapter_instance,
			$export_writer_instance,
			get_current_user_id(),
			$configuration_id,
			$batch_size
		);

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			$this->redirect_overview_with_error(
				'1',
				'configuration_export_persistence_failed',
				is_wp_error( $post_id ) ? $post_id : null,
				[
					'configuration_id' => $configuration_id,
					'post_id'          => is_wp_error( $post_id ) ? 0 : $post_id,
				]
			);
		}

		try {
			$this->repository->mark_queued( $post_id );
			$this->message_bus->dispatch( new StartExportMessage( $post_id, $export_writer ) );
			ExportEventDispatcher::dispatch(
				ExportEvents::QUEUED,
				$post_id,
				[
					'action'           => 'storeaccountant_start_export_from_overview',
					'export_id'        => $post_id,
					'configuration_id' => $configuration_id,
					'renderer_id'      => $export_writer,
				]
			);
			$this->loopback_dispatcher->maybe_dispatch_for_manual_export( $post_id );
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$post_id,
				__( 'The accounting export could not be queued.', 'storeaccountant' ),
				$exception,
				[
					'action'      => 'storeaccountant_start_export_from_overview',
					'export_id'   => $post_id,
					'log_message' => 'The accounting export could not be queued.',
				]
			);
			$this->redirect_overview_with_error( '1', 'configuration_export_queue_failed', null, [ 'export_id' => $post_id ], $exception );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'                      => ExportPostType::POST_TYPE,
					'storeaccountant_export_created' => (string) $post_id,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Handles quick export form submissions.
	 */
	private function handle_quick_export(): void {
		check_admin_referer( 'storeaccountant_start_export', 'storeaccountant_export_nonce' );

		$draft = $this->get_quick_export_draft();

		if ( null === $draft ) {
			$this->redirect_to_quick_export_start();
		}

		$request             = Request::post_data();
		$title               = trim( Request::post_text( 'storeaccountant_export_title' ) );
		$storage_engine      = Request::post_key( 'storeaccountant_storage_engine' );
		$export_adapter      = $draft['export_adapter'];
		$export_writer       = Request::post_key( 'storeaccountant_export_writer', CsvExportRenderer::RENDERER_ID );
		$batch_size          = $this->settings_fields->get_batch_size_from_request( $request );
		$password            = Request::post_text( 'storeaccountant_export_download_password' );
		$filters             = $this->get_filter_selections_from_request( $export_adapter, $request );
		$tax_provider_id     = $this->settings_fields->get_tax_provider_id_from_request( $export_adapter, $request );
		$additional_settings = $this->settings_fields->get_additional_settings_from_request( $export_adapter, $request );

		if ( '' !== $title ) {
			$this->save_quick_export_draft( $title, $export_adapter );
		}

		if ( is_wp_error( $batch_size ) ) {
			$this->redirect_with_error( 'invalid_batch_size', 'invalid_batch_size', $batch_size, [], null, true );
		}

		if ( is_wp_error( $filters ) || ! $this->storage_adapters->is_enabled( $storage_engine ) ) {
			$this->redirect_with_error(
				'1',
				is_wp_error( $filters ) ? 'quick_export_filters_invalid' : 'quick_export_storage_adapter_not_enabled',
				is_wp_error( $filters ) ? $filters : null,
				[ 'storage_engine' => $storage_engine ],
				null,
				true
			);
		}

		if ( OrderExportAdapter::ADAPTER_ID === $export_adapter && '' === $tax_provider_id ) {
			$this->redirect_with_error( '1', 'quick_export_tax_provider_missing', null, [ 'export_adapter' => $export_adapter ], null, true );
		}

		if ( is_wp_error( $additional_settings ) ) {
			$this->redirect_with_error( '1', 'quick_export_additional_settings_invalid', $additional_settings, [ 'export_adapter' => $export_adapter ], null, true );
		}

		$export_adapter_instance = $this->export_adapters->get( $export_adapter );
		$export_writer_instance  = $this->export_writers->get( $export_writer );

		if ( null === $export_adapter_instance || null === $export_writer_instance ) {
			$this->redirect_with_error(
				'1',
				'quick_export_adapter_or_writer_missing',
				null,
				[
					'export_adapter' => $export_adapter,
					'export_writer'  => $export_writer,
				],
				null,
				true
			);
		}

		$this->validate_export_title(
			$title,
			fn ( string $error, string $reason ): never => $this->redirect_with_error( $error, $reason, null, [], null, true )
		);

		$effective_password = $this->passwords->get_password_for_submission( $password );

		if ( is_wp_error( $effective_password ) ) {
			$this->redirect_with_error( '1', 'quick_export_password_submission_failed', $effective_password, [], null, true );
		}

		if ( str_contains( $title, $effective_password ) ) {
			$this->redirect_with_error( 'title_contains_password', 'title_contains_password', null, [], null, true );
		}

		$password_snapshot = $this->passwords->get_snapshot_for_submission( $password );

		if ( is_wp_error( $password_snapshot ) ) {
			$this->redirect_with_error( '1', 'quick_export_password_snapshot_failed', $password_snapshot, [], null, true );
		}

		$post_id = $this->repository->create(
			$title,
			$filters,
			$storage_engine,
			$export_adapter_instance,
			$export_writer_instance,
			get_current_user_id(),
			null,
			$batch_size,
			$password_snapshot,
			$additional_settings,
			$tax_provider_id
		);

		if ( is_wp_error( $post_id ) || $post_id <= 0 ) {
			$this->redirect_with_error(
				'1',
				'quick_export_persistence_failed',
				is_wp_error( $post_id ) ? $post_id : null,
				[ 'post_id' => is_wp_error( $post_id ) ? 0 : $post_id ],
				null,
				true
			);
		}

		$this->delete_quick_export_draft();

		try {
			$this->repository->mark_queued( $post_id );
			$this->message_bus->dispatch( new StartExportMessage( $post_id, $export_writer ) );
			ExportEventDispatcher::dispatch(
				ExportEvents::QUEUED,
				$post_id,
				[
					'action'      => 'storeaccountant_start_export',
					'export_id'   => $post_id,
					'renderer_id' => $export_writer,
				]
			);
			$this->loopback_dispatcher->maybe_dispatch_for_manual_export( $post_id );
		} catch ( Throwable $exception ) {
			$this->repository->mark_failed(
				$post_id,
				__( 'The accounting export could not be queued.', 'storeaccountant' ),
				$exception,
				[
					'action'      => 'storeaccountant_start_export',
					'export_id'   => $post_id,
					'log_message' => 'The accounting export could not be queued.',
				]
			);
			$this->redirect_with_error( '1', 'quick_export_queue_failed', null, [ 'export_id' => $post_id ], $exception );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'                      => ExportPostType::POST_TYPE,
					'storeaccountant_export_created' => (string) $post_id,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Gets the current quick export draft when the details step should be rendered.
	 *
	 * @return array{title: string, export_adapter: string}|null
	 */
	private function get_quick_export_draft_for_render(): ?array {
		if ( self::QUICK_EXPORT_DETAILS_STEP !== Request::get_key( 'storeaccountant_quick_export_step' ) ) {
			return null;
		}

		$draft = $this->get_quick_export_draft();

		if ( null === $draft ) {
			$this->redirect_to_quick_export_start();
		}

		return $draft;
	}

	/**
	 * Gets the current quick export draft.
	 *
	 * @return array{title: string, export_adapter: string}|null
	 */
	private function get_quick_export_draft(): ?array {
		$draft = get_user_meta( get_current_user_id(), self::QUICK_EXPORT_DRAFT_META_KEY, true );

		if ( ! is_array( $draft ) ) {
			return null;
		}

		$title          = trim( (string) ( $draft['title'] ?? '' ) );
		$export_adapter = (string) ( $draft['export_adapter'] ?? '' );

		if ( '' === $title || '' === $export_adapter || null === $this->export_adapters->get( $export_adapter ) ) {
			$this->delete_quick_export_draft();

			return null;
		}

		return [
			'title'          => $title,
			'export_adapter' => $export_adapter,
		];
	}

	/**
	 * Saves the current user's quick export draft.
	 */
	private function save_quick_export_draft( string $title, string $export_adapter ): void {
		update_user_meta(
			get_current_user_id(),
			self::QUICK_EXPORT_DRAFT_META_KEY,
			[
				'title'          => $title,
				'export_adapter' => $export_adapter,
			]
		);
	}

	/**
	 * Deletes the current user's quick export draft.
	 */
	private function delete_quick_export_draft(): void {
		delete_user_meta( get_current_user_id(), self::QUICK_EXPORT_DRAFT_META_KEY );
	}

	/**
	 * Redirects to the first quick export step.
	 */
	private function redirect_to_quick_export_start(): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page' => self::PAGE_SLUG,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Redirects to the quick export details step.
	 */
	private function redirect_to_quick_export_details(): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'                              => self::PAGE_SLUG,
					'storeaccountant_quick_export_step' => self::QUICK_EXPORT_DETAILS_STEP,
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Validates an export title shared by quick exports and configuration-based exports.
	 *
	 * @param string   $title               Export title.
	 * @param callable $redirect_with_error Error redirect callback.
	 */
	private function validate_export_title( string $title, callable $redirect_with_error ): void {
		if ( '' === $title ) {
			$redirect_with_error( 'missing_title', 'missing_title' );
		}

		if ( $this->repository->exists_with_title( $title ) ) {
			$redirect_with_error( 'duplicate_title', 'duplicate_title' );
		}
	}

	/**
	 * Gets export filter selections from request data and snapshots dynamic values.
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

		return $this->filter_snapshotter->snapshot( $filters );
	}

	/**
	 * Gets the batch size stored on an export configuration.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 */
	private function get_batch_size_from_configuration( int $configuration_id ): int {
		$batch_size = (int) get_post_meta( $configuration_id, ExportConfigurationPostType::META_BATCH_SIZE, true );

		return $batch_size >= ExportPostType::MIN_BATCH_SIZE ? $batch_size : ExportPostType::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Renders admin notices for export form submissions.
	 */
	private function render_notice(): void {
		$export_notice = $this->get_export_notice();

		if ( 'created' === $export_notice ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'The accounting export was saved and queued.', 'storeaccountant' ); ?></p>
			</div>
			<?php
			return;
		}

		if ( '' !== $export_notice ) {
			$message = __( 'The accounting export could not be saved.', 'storeaccountant' );

			if ( 'duplicate_title' === $export_notice ) {
				$message = __( 'An accounting export with this title already exists. Choose a unique export title.', 'storeaccountant' );
			}

			if ( 'missing_title' === $export_notice ) {
				$message = __( 'Enter an export title before starting the export.', 'storeaccountant' );
			}

			if ( 'invalid_batch_size' === $export_notice ) {
				$message = __( 'Enter a numeric batch size of at least 10.', 'storeaccountant' );
			}

			if ( 'title_contains_password' === $export_notice ) {
				$message = __( 'The export title must not contain the download password.', 'storeaccountant' );
			}
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
				<?php $this->render_diagnostic_notice(); ?>
			</div>
			<?php
		}

		foreach ( $this->storage_adapters->get_enabled() as $storage_engine ) {
			$storage_result = $storage_engine->ensure();

			if ( ! is_wp_error( $storage_result ) ) {
				continue;
			}
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html(
						__( 'StoreAccountant could not prepare the selected storage location. Please check the storage permissions and configuration.', 'storeaccountant' )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Redirects back to the form with an error notice.
	 */
	private function redirect_with_error( string $error = '1', string $reason = 'unknown', ?WP_Error $wp_error = null, array $context = [], ?Throwable $throwable = null, bool $details_step = false ): void {
		$incident = $this->log_error( 'quick_export', $reason, $wp_error, $context, $throwable );
		$args     = [
			'page'                         => self::PAGE_SLUG,
			'storeaccountant_export_error' => $error,
		];

		if ( $details_step ) {
			$args['storeaccountant_quick_export_step'] = self::QUICK_EXPORT_DETAILS_STEP;
		}

		if ( null !== $incident ) {
			$args['storeaccountant_diagnostic_support_id'] = $incident->support_id;
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
	 * Redirects back to the export overview with an error notice.
	 */
	private function redirect_overview_with_error( string $error = '1', string $reason = 'unknown', ?WP_Error $wp_error = null, array $context = [], ?Throwable $throwable = null ): void {
		$incident = $this->log_error( 'configuration_export', $reason, $wp_error, $context, $throwable );
		$args     = [
			'post_type'                    => ExportPostType::POST_TYPE,
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
	 * Logs a diagnostic incident for export creation failures.
	 *
	 * @param string               $source    Incident source.
	 * @param string               $reason    Internal failure reason.
	 * @param WP_Error|null        $wp_error  Optional WordPress error.
	 * @param array<string, mixed> $context   Additional safe diagnostic context.
	 * @param Throwable|null       $throwable Optional throwable.
	 */
	private function log_error( string $source, string $reason, ?WP_Error $wp_error = null, array $context = [], ?Throwable $throwable = null ): ?DiagnosticIncident {
		return $this->diagnostics->error(
			$source,
			__( 'The accounting export could not be saved.', 'storeaccountant' ),
			[
				'reason'  => $reason,
				'context' => $context,
			],
			$wp_error,
			$throwable
		);
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
	 * Highlights the accounting exports submenu while rendering hidden StoreAccountant pages.
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
	 * Checks whether the current admin page belongs to StoreAccountant.
	 */
	private function is_current_plugin_page(): bool {
		return self::PAGE_SLUG === Request::get_key( 'page' );
	}

	/**
	 * Gets the current redirect notice code.
	 */
	private function get_export_notice(): string {
		if ( Request::has_get( 'storeaccountant_export_created' ) ) {
			return 'created';
		}

		if ( ! Request::has_get( 'storeaccountant_export_error' ) ) {
			return '';
		}

		return Request::get_key( 'storeaccountant_export_error' );
	}

	/**
	 * Gets the current hidden page title.
	 */
	private function get_page_title(): string {
		return __( 'Create New Export', 'storeaccountant' );
	}
}
