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

use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\I18n;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function str_replace;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the accounting export start form.
 */
final readonly class AccountingExportPageForm {
	/**
	 * Initializes the form.
	 *
	 * @param StorageAdapterRegistry            $storage_adapters          storage adapter registry.
	 * @param ExportAdapterRegistry             $export_adapters         Export adapter registry.
	 * @param ExportRendererRegistry            $export_writers          Export writer registry.
	 * @param ExportFilterFieldProviderRegistry $filter_field_providers Export filter field providers.
	 * @param DownloadPasswordManager           $passwords              Download password manager.
	 * @param PermissionChecker                 $permissions            Permission checker.
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private ExportAdapterRegistry $export_adapters,
		private ExportRendererRegistry $export_writers,
		private ExportFilterFieldProviderRegistry $filter_field_providers,
		private DownloadPasswordManager $passwords,
		private PermissionChecker $permissions
	) {}

	/**
	 * Renders the quick export form.
	 */
	public function render(): void {
		?>
		<div class="storeaccountant-content-panel">
			<?php $this->render_quick_export_form(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the quick export form.
	 */
	private function render_quick_export_form(): void {
		$storage_adapters = $this->storage_adapters->get_enabled();
		$export_adapters  = $this->export_adapters->get_all();
		$export_writers   = $this->export_writers->get_all();
		$submit_disabled  = [] === $storage_adapters || [] === $export_adapters || [] === $export_writers;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="storeaccountant_start_export" />
			<input type="hidden" name="storeaccountant_quick_export" value="1" />
			<?php wp_nonce_field( 'storeaccountant_start_export', 'storeaccountant_export_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="storeaccountant-export-title"><?php esc_html_e( 'Title', 'storeaccountant' ); ?></label>
						</th>
							<td>
								<input
									type="text"
									id="storeaccountant-export-title"
									name="storeaccountant_export_title"
									class="regular-text"
									value=""
									required="required"
								/>
							</td>
						</tr>
					<tr>
						<th scope="row">
							<label for="storeaccountant-export-adapter"><?php esc_html_e( 'Export Type', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<?php if ( [] === $export_adapters ) : ?>
								<p class="description"><?php esc_html_e( 'No export adapters are available. Register at least one export adapter before starting an export.', 'storeaccountant' ); ?></p>
							<?php else : ?>
								<select id="storeaccountant-export-adapter" name="storeaccountant_export_adapter" data-storeaccountant-export-adapter-select="1">
									<?php foreach ( $export_adapters as $adapter ) : ?>
									<option value="<?php echo esc_attr( $adapter->get_id() ); ?>">
										<?php
											echo esc_html( I18n::translate_registry_label( 'export_adapter_', $adapter->get_id() ) );
										?>
									</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
					<?php $this->render_filter_field_provider_groups( OrderExportAdapter::ADAPTER_ID ); ?>
					<tr>
						<th scope="row">
							<label for="storeaccountant-export-writer"><?php esc_html_e( 'Export Format', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<?php if ( [] === $export_writers ) : ?>
								<p class="description"><?php esc_html_e( 'No export formats are available. Register at least one export renderer before starting an export.', 'storeaccountant' ); ?></p>
							<?php else : ?>
								<select id="storeaccountant-export-writer" name="storeaccountant_export_writer">
									<?php foreach ( $export_writers as $writer ) : ?>
									<option value="<?php echo esc_attr( $writer->get_id() ); ?>">
										<?php
										echo esc_html( I18n::translate_registry_label( 'exporter_', $writer->get_id() ) );
										?>
									</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="storeaccountant-storage-engine"><?php esc_html_e( 'Storage Location', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<?php if ( [] === $storage_adapters ) : ?>
								<p class="description"><?php esc_html_e( 'No storage locations are enabled. Enable at least one storage location before starting an export.', 'storeaccountant' ); ?></p>
							<?php else : ?>
								<select id="storeaccountant-storage-engine" name="storeaccountant_storage_engine">
									<?php foreach ( $storage_adapters as $storage_adapter ) : ?>
									<option value="<?php echo esc_attr( $storage_adapter->get_id() ); ?>">
										<?php
										echo esc_html( I18n::translate_registry_label( 'storage_adapter_', $storage_adapter->get_id() ) );
										?>
									</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						</td>
					</tr>
					<?php $this->render_download_password_row(); ?>
						<tr>
						<th scope="row">
							<label for="storeaccountant-export-batch-size"><?php esc_html_e( 'Batch Size', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="storeaccountant-export-batch-size"
								name="storeaccountant_export_batch_size"
								class="small-text"
								value="<?php echo esc_attr( (string) ExportPostType::DEFAULT_BATCH_SIZE ); ?>"
								min="<?php echo esc_attr( (string) ExportPostType::MIN_BATCH_SIZE ); ?>"
								step="1"
								required="required"
							/>
							<p class="description"><?php esc_html_e( 'StoreAccountant automatically splits the export into batches. This value defines how many items are exported in each batch; it does not limit the total number of exported items. The minimum is 10. If you are unsure, leave it at 100.', 'storeaccountant' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Start Quick Export', 'storeaccountant' ), 'primary', 'storeaccountant_start_quick_export', false, $submit_disabled ? [ 'disabled' => 'disabled' ] : [] ); ?>
		</form>
		<?php
	}

		/**
		 * Renders the quick export download password field.
		 */
	private function render_download_password_row(): void {
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-export-download-password"><?php esc_html_e( 'Download password', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( ! $this->passwords->is_available() ) : ?>
					<p class="description"><?php esc_html_e( 'Download passwords are unavailable because this server provides neither Sodium nor OpenSSL encryption.', 'storeaccountant' ); ?></p>
				<?php else : ?>
					<input
						type="password"
						id="storeaccountant-export-download-password"
						name="storeaccountant_export_download_password"
						class="regular-text"
						value=""
						autocomplete="new-password"
					/>
					<p class="description"><?php esc_html_e( 'Enter a download password for this export. Leave empty to store the current global download password.', 'storeaccountant' ); ?></p>
					<?php if ( $this->permissions->can( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS ) ) : ?>
						<?php $password = $this->passwords->get_password_for_submission( '' ); ?>
						<?php if ( ! is_wp_error( $password ) ) : ?>
							<p>
								<label><?php esc_html_e( 'Current Download Password', 'storeaccountant' ); ?></label><br />
								<input type="text" class="regular-text" value="<?php echo esc_attr( $password ); ?>" readonly="readonly" disabled="disabled" />
							</p>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders default filter fields for each registered export adapter.
	 *
	 * @param string $active_export_type Initially active export type.
	 */
	private function render_filter_field_provider_groups( string $active_export_type ): void {
		foreach ( $this->export_adapters->get_all() as $adapter ) {
			$export_type = $adapter->get_id();
			$hidden      = $active_export_type !== $export_type;

			foreach ( $this->filter_field_providers->get_providers( $export_type ) as $provider ) {
				ob_start();
				$provider->render( $provider->get_default_selection() );
				$rows = (string) ob_get_clean();
				$rows = str_replace(
					'<tr',
					'<tr data-storeaccountant-export-filter-group="1" data-storeaccountant-export-type="' . esc_attr( $export_type ) . '"' . ( $hidden ? ' class="storeaccountant-is-hidden"' : '' ),
					$rows
				);

					echo wp_kses( $rows, $this->get_allowed_filter_row_html() );
			}
		}
	}

	/**
	 * Gets HTML allowed for filter field rows rendered by registered providers.
	 *
	 * @return array<string, array<string, true|array<string, true>>>
	 */
	private function get_allowed_filter_row_html(): array {
		$global_attributes = [
			'aria-describedby' => true,
			'aria-label'       => true,
			'class'            => true,
			'data-*'           => true,
			'for'              => true,
			'id'               => true,
			'name'             => true,
			'style'            => true,
			'title'            => true,
			'type'             => true,
			'value'            => true,
		];

		return [
			'br'       => [],
			'div'      => $global_attributes,
			'fieldset' => $global_attributes,
			'input'    => $global_attributes + [
				'checked'     => true,
				'disabled'    => true,
				'max'         => true,
				'min'         => true,
				'placeholder' => true,
				'readonly'    => true,
				'required'    => true,
				'step'        => true,
			],
			'label'    => $global_attributes,
			'legend'   => $global_attributes,
			'option'   => $global_attributes + [
				'selected' => true,
			],
			'p'        => $global_attributes,
			'select'   => $global_attributes + [
				'disabled' => true,
				'multiple' => true,
				'required' => true,
			],
			'span'     => $global_attributes,
			'tbody'    => $global_attributes,
			'td'       => $global_attributes + [
				'colspan' => true,
				'rowspan' => true,
			],
			'th'       => $global_attributes + [
				'colspan' => true,
				'rowspan' => true,
				'scope'   => true,
			],
			'tr'       => $global_attributes,
		];
	}
}
