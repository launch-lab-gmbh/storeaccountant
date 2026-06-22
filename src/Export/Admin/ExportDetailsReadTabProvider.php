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

use WP_Post;
use StoreAccountant\Admin\AdminDateFormatter;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportReadTabProviderInterface;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\I18n;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function array_filter;
use function array_map;
use function explode;
use function implode;
use function is_string;
use function ltrim;
use function sprintf;
use function strlen;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the read-only details tab for saved exports.
 */
final readonly class ExportDetailsReadTabProvider implements ExportReadTabProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'export_details';
	public const TAB_ID      = 'export-details';

	public function __construct(
		private ExportAdapterRegistry $adapter_registry,
		private ExportRendererRegistry $writer_registry,
		private StorageAdapterRegistry $storage_adapters,
		private ExportStoragePathGenerator $storage_path_generator,
		private ExportDownloadUrlFactory $download_urls,
		private DownloadPasswordManager $passwords,
		private PermissionChecker $permissions
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_read_tab_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( WP_Post $export ): bool {
		return ExportPostType::POST_TYPE === $export->post_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_tabs( WP_Post $export ): array {
		return [
			self::TAB_ID => __( 'Export Details', 'storeaccountant' ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( string $tab, WP_Post $export ): void {
		if ( self::TAB_ID !== $tab ) {
			return;
		}
		?>
			<table class="widefat striped storeaccountant-detail-table" role="presentation">
				<tbody>
					<?php $this->render_detail_row( __( 'Export ID', 'storeaccountant' ), (string) $export->ID ); ?>
					<?php $this->render_detail_row( __( 'Title', 'storeaccountant' ), get_the_title( $export ) ); ?>
					<?php $this->render_detail_row( __( 'Record Status', 'storeaccountant' ), $this->get_post_status_label( $export ) ); ?>
					<?php $this->render_detail_row( __( 'Export Status', 'storeaccountant' ), $this->get_export_status_label( $export->ID ) ); ?>
					<?php $this->render_error_detail_row( $export->ID ); ?>
					<?php $this->render_detail_row( __( 'Current Step', 'storeaccountant' ), (string) get_post_meta( $export->ID, ExportPostType::META_CURRENT_STEP, true ) ); ?>
					<?php $this->render_detail_row( __( 'Batches', 'storeaccountant' ), $this->format_progress( $export->ID, ExportPostType::META_PROCESSED_BATCHES, ExportPostType::META_TOTAL_BATCHES ) ); ?>
					<?php $this->render_detail_row( __( 'Items', 'storeaccountant' ), $this->format_items( $export->ID ) ); ?>
					<?php $this->render_detail_row( __( 'Progress', 'storeaccountant' ), $this->format_percentage( $export->ID ) ); ?>
					<?php $this->render_detail_row( __( 'Started At', 'storeaccountant' ), $this->format_datetime( (string) get_post_meta( $export->ID, ExportPostType::META_STARTED_AT, true ) ) ); ?>
					<?php $this->render_detail_row( __( 'Finished At', 'storeaccountant' ), $this->format_datetime( (string) get_post_meta( $export->ID, ExportPostType::META_FINISHED_AT, true ) ) ); ?>
					<?php $this->render_detail_row( __( 'Runtime', 'storeaccountant' ), $this->format_runtime( $export->ID ) ); ?>
					<?php $this->render_detail_row( __( 'Exported At', 'storeaccountant' ), $this->format_datetime( (string) get_post_meta( $export->ID, ExportPostType::META_EXPORTED_AT, true ) ) ); ?>
					<?php $this->render_detail_row( __( 'Export Type', 'storeaccountant' ), $this->format_export_adapter( $this->get_export_adapter_id( $export->ID ) ) ); ?>
					<?php $this->render_detail_row( __( 'Export Format', 'storeaccountant' ), $this->format_export_writer( $this->get_export_writer_id( $export->ID ) ) ); ?>
					<?php $this->render_detail_row( __( 'Storage Location', 'storeaccountant' ), $this->format_storage_adapter( (string) get_post_meta( $export->ID, ExportPostType::META_STORAGE_ENGINE, true ) ) ); ?>
					<?php $this->render_detail_row( __( 'Storage Reference', 'storeaccountant' ), $this->get_storage_display_path( $export->ID ) ); ?>
					<?php $this->render_detail_row( __( 'File Size', 'storeaccountant' ), $this->format_file_size( $export->ID ) ); ?>
					<?php $this->render_detail_row( __( 'Triggered By', 'storeaccountant' ), $this->get_triggered_by_label( $export->ID ) ); ?>
					<?php $this->render_configuration_detail_row( $export->ID ); ?>
					<?php $this->render_download_detail_row( $export->ID ); ?>
					<?php $this->render_download_password_detail_row( $export->ID ); ?>
				</tbody>
			</table>
			<?php
	}

	private function render_detail_row( string $label, string $value ): void {
		$value = '' !== $value ? $value : __( 'Not set', 'storeaccountant' );
		?>
		<tr>
			<th scope="row">
				<?php echo esc_html( $label ); ?>
			</th>
			<td><?php echo esc_html( $value ); ?></td>
		</tr>
		<?php
	}

	private function render_configuration_detail_row( int $post_id ): void {
		$configuration_id = (int) get_post_meta( $post_id, ExportPostType::META_CONFIGURATION_ID, true );
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configuration', 'storeaccountant' ); ?></th>
			<td>
				<?php
				if ( $configuration_id <= 0 ) {
					esc_html_e( 'Quick Export', 'storeaccountant' );
				} else {
					$this->render_configuration( $configuration_id );
				}
				?>
			</td>
		</tr>
		<?php
	}

	private function render_configuration( int $configuration_id ): void {
		$configuration = get_post( $configuration_id );

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type ) {
			echo esc_html__( 'Deleted configuration', 'storeaccountant' );
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

	private function render_download_detail_row( int $post_id ): void {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Download', 'storeaccountant' ); ?></th>
			<td><?php $this->render_download_button( $post_id ); ?></td>
		</tr>
		<?php
	}

	private function render_download_password_detail_row( int $post_id ): void {
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Download Password', 'storeaccountant' ); ?></th>
			<td>
				<?php if ( ! $this->permissions->can( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS, $post_id ) ) : ?>
					<?php esc_html_e( 'Protected', 'storeaccountant' ); ?>
					<?php else : ?>
						<?php $password = $this->passwords->reveal_export_password( $post_id ); ?>
						<?php if ( is_wp_error( $password ) ) : ?>
							<?php esc_html_e( 'Unavailable', 'storeaccountant' ); ?>
						<?php else : ?>
							<?php echo esc_html( $password ); ?>
						<?php endif; ?>
					<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function render_error_detail_row( int $post_id ): void {
		$error_message = (string) get_post_meta( $post_id, ExportPostType::META_ERROR_MESSAGE, true );

		if ( '' === $error_message ) {
			return;
		}
		?>
			<tr>
			<th scope="row">
				<?php esc_html_e( 'Error Message', 'storeaccountant' ); ?>
			</th>
			<td>
				<?php echo esc_html( $error_message ); ?>
				<p class="description">
					<?php if ( $this->permissions->can( PermissionActionIds::EXPORT_VIEW_LOG, $post_id ) ) : ?>
						<?php
						printf(
							/* translators: %s: technical export log link */
							esc_html__( 'Additional technical details are available in the %s.', 'storeaccountant' ),
							sprintf(
								'<a href="%1$s">%2$s</a>',
								esc_url( $this->get_log_tab_url( $post_id ) ),
								esc_html__( 'export log', 'storeaccountant' )
							)
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'An administrator with export log access can review additional technical details in the export log.', 'storeaccountant' ); ?>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<?php
	}

	private function render_download_button( int $post_id ): void {
		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $post_id, ExportPostType::META_PATH, true ) );
		$status       = (string) get_post_meta( $post_id, ExportPostType::META_STATUS, true );

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

	private function render_disabled_download_button( string $message ): void {
		printf(
			'<span class="storeaccountant-disabled-download" title="%1$s"><button type="button" class="button button-small" disabled="disabled">%2$s</button></span>',
			esc_attr( $message ),
			esc_html__( 'Download', 'storeaccountant' )
		);
	}

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

	private function get_log_tab_url( int $post_id ): string {
		return add_query_arg(
			[
				'page'      => 'storeaccountant-export',
				'export_id' => (string) $post_id,
				'tab'       => ExportLogReadTabProvider::TAB_ID,
			],
			admin_url( 'admin.php' )
		);
	}

	private function format_datetime( string $datetime ): string {
		return AdminDateFormatter::format_mysql_datetime( $datetime );
	}

	private function get_export_adapter_id( int $post_id ): string {
		return (string) get_post_meta( $post_id, ExportPostType::META_EXPORT_ADAPTER, true );
	}

	private function get_export_writer_id( int $post_id ): string {
		$writer_id = (string) get_post_meta( $post_id, ExportPostType::META_EXPORT_WRITER, true );

		return '' !== $writer_id ? $writer_id : CsvExportRenderer::RENDERER_ID;
	}

	private function format_export_adapter( string $export_adapter ): string {
		$adapter = $this->adapter_registry->get( $export_adapter );

		if ( null !== $adapter ) {
			return I18n::translate_registry_label( 'export_adapter_', $adapter->get_id() );
		}

		return I18n::translate_registry_label( 'export_adapter_', $export_adapter );
	}

	private function format_export_writer( string $export_writer ): string {
		$writer = $this->writer_registry->get( $export_writer );

		if ( null !== $writer ) {
			return I18n::translate_registry_label( 'exporter_', $writer->get_id() );
		}

		return I18n::translate_registry_label( 'exporter_', $export_writer );
	}

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

	private function get_storage_display_path( int $post_id ): string {
		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $post_id, ExportPostType::META_PATH, true ) );

		if ( '' === $storage_path ) {
			return '';
		}

		$storage_adapter = $this->get_storage_adapter( $post_id );

		return null !== $storage_adapter ? $this->storage_path_generator->get_display_path( $storage_adapter->get_id(), $storage_path ) : $storage_path;
	}

	private function get_triggered_by_label( int $post_id ): string {
		$user = get_user_by( 'id', (int) get_post_meta( $post_id, ExportPostType::META_TRIGGERED_BY, true ) );

		return $user ? $user->display_name : __( 'Unknown', 'storeaccountant' );
	}

	private function get_post_status_label( WP_Post $post ): string {
		$status = get_post_status_object( $post->post_status );

		return $status ? $status->label : $post->post_status;
	}

	private function get_export_status_label( int $post_id ): string {
		$status = (string) get_post_meta( $post_id, ExportPostType::META_STATUS, true );

		if ( '' === $status ) {
			$status = '' !== $this->normalize_storage_path( (string) get_post_meta( $post_id, ExportPostType::META_PATH, true ) )
				? ExportStatus::COMPLETED
				: ExportStatus::SCHEDULED;
		}

		return ExportStatus::get_label( $status );
	}

	private function format_progress( int $post_id, string $processed_meta_key, string $total_meta_key ): string {
		$processed = (int) get_post_meta( $post_id, $processed_meta_key, true );
		$total     = (int) get_post_meta( $post_id, $total_meta_key, true );

		if ( $total <= 0 ) {
			return '';
		}

		return sprintf(
			'%1$d / %2$d',
			$processed,
			$total
		);
	}

	private function format_items( int $post_id ): string {
		$total = (int) get_post_meta( $post_id, ExportPostType::META_TOTAL_ITEMS, true );

		if ( $total <= 0 ) {
			return __( 'No items found for this export.', 'storeaccountant' );
		}

		return $this->format_progress( $post_id, ExportPostType::META_PROCESSED_ITEMS, ExportPostType::META_TOTAL_ITEMS );
	}

	private function format_percentage( int $post_id ): string {
		$total     = (int) get_post_meta( $post_id, ExportPostType::META_TOTAL_BATCHES, true );
		$processed = (int) get_post_meta( $post_id, ExportPostType::META_PROCESSED_BATCHES, true );

		if ( $total <= 0 ) {
			return '';
		}

		return sprintf( '%d%%', min( 100, (int) round( ( $processed / $total ) * 100 ) ) );
	}

	private function format_runtime( int $post_id ): string {
		$started_at  = $this->get_timestamp( (string) get_post_meta( $post_id, ExportPostType::META_STARTED_AT, true ) );
		$finished_at = $this->get_timestamp( (string) get_post_meta( $post_id, ExportPostType::META_FINISHED_AT, true ) );

		if ( null === $started_at ) {
			return '';
		}

		$seconds = max( 0, ( null !== $finished_at ? $finished_at : time() ) - $started_at );

		return sprintf(
			/* translators: %d: runtime in seconds */
			_n( '%d second', '%d seconds', $seconds, 'storeaccountant' ),
			$seconds
		);
	}

	private function format_file_size( int $post_id ): string {
		$storage_path    = $this->normalize_storage_path( (string) get_post_meta( $post_id, ExportPostType::META_PATH, true ) );
		$storage_adapter = $this->get_storage_adapter( $post_id );

		if ( '' === $storage_path || null === $storage_adapter || ! $storage_adapter->file_exists( $storage_path ) ) {
			return '';
		}

		$file_size = $storage_adapter->get_file_size( $storage_path );

		return is_wp_error( $file_size ) ? '' : size_format( $file_size );
	}

	private function get_timestamp( string $datetime ): ?int {
		if ( '' === $datetime ) {
			return null;
		}

		$timestamp = strtotime( $datetime . ' UTC' );

		return false !== $timestamp ? $timestamp : null;
	}

	private function get_storage_adapter( int $post_id ): ?StorageAdapterInterface {
		$storage_engine = (string) get_post_meta( $post_id, ExportPostType::META_STORAGE_ENGINE, true );

		return '' !== $storage_engine ? $this->storage_adapters->get( sanitize_key( $storage_engine ) ) : null;
	}

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
