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
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\I18n;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function str_replace;
use function trim;

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
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportAdapterRegistry             $export_adapters         Export adapter registry.
	 * @param ExportFilterFieldProviderRegistry $filter_field_providers Export filter field providers.
	 * @param ExportSettingsFields              $settings_fields        Shared export settings fields.
	 * @param DownloadPasswordManager           $passwords              Download password manager.
	 * @param PermissionChecker                 $permissions            Permission checker.
	 */
	public function __construct(
		private ExportAdapterRegistry $export_adapters,
		private ExportFilterFieldProviderRegistry $filter_field_providers,
		private ExportSettingsFields $settings_fields,
		private DownloadPasswordManager $passwords,
		private PermissionChecker $permissions
	) {}

	/**
	 * Renders the quick export form.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array{title: string, export_adapter: string}|null $draft Current quick export draft.
	 */
	public function render( ?array $draft = null ): void {
		?>
		<div class="storeaccountant-content-panel">
			<?php $this->render_quick_export_form( $draft ); ?>
		</div>
		<?php
	}

	/**
	 * Renders the quick export form.
	 *
	 * @param array{title: string, export_adapter: string}|null $draft Current quick export draft.
	 */
	private function render_quick_export_form( ?array $draft ): void {
		$export_adapters = $this->export_adapters->get_all();
		$is_details_step = null !== $draft;
		$submit_disabled = [] === $export_adapters || ( $is_details_step && ! $this->settings_fields->has_required_choices() );
		$title           = trim( $draft['title'] ?? '' );
		$export_adapter  = $draft['export_adapter'] ?? OrderExportAdapter::ADAPTER_ID;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="storeaccountant_start_export" />
			<input type="hidden" name="<?php echo esc_attr( $is_details_step ? 'storeaccountant_quick_export' : 'storeaccountant_quick_export_prepare' ); ?>" value="1" />
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
								value="<?php echo esc_attr( $title ); ?>"
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
								<?php if ( $is_details_step ) : ?>
									<input type="hidden" name="storeaccountant_export_adapter" value="<?php echo esc_attr( $export_adapter ); ?>" />
								<?php endif; ?>
								<select id="storeaccountant-export-adapter" name="<?php echo esc_attr( $is_details_step ? 'storeaccountant_export_adapter_display' : 'storeaccountant_export_adapter' ); ?>" required="required" <?php disabled( $is_details_step ); ?>>
									<?php foreach ( $export_adapters as $adapter ) : ?>
									<option value="<?php echo esc_attr( $adapter->get_id() ); ?>" <?php selected( $export_adapter, $adapter->get_id() ); ?>>
										<?php
											echo esc_html( I18n::translate_registry_label( 'export_adapter_', $adapter->get_id() ) );
										?>
									</option>
									<?php endforeach; ?>
								</select>
								<?php if ( $is_details_step ) : ?>
									<p class="description"><?php esc_html_e( 'The export type cannot be changed after the quick export details have been started.', 'storeaccountant' ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $is_details_step ) : ?>
						<?php $this->render_filter_field_provider_groups( $export_adapter ); ?>
						<?php $this->settings_fields->render( $export_adapter, include_batch_size: false, empty_state_context: 'export' ); ?>
						<?php $this->render_download_password_row(); ?>
						<?php $this->settings_fields->render_batch_size_row( ExportPostType::DEFAULT_BATCH_SIZE ); ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php submit_button( $is_details_step ? __( 'Start Quick Export', 'storeaccountant' ) : __( 'Continue', 'storeaccountant' ), 'primary', $is_details_step ? 'storeaccountant_start_quick_export' : 'storeaccountant_prepare_quick_export', false, $submit_disabled ? [ 'disabled' => 'disabled' ] : [] ); ?>
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
	 * Renders default filter fields for the selected export adapter.
	 *
	 * @param string $active_export_type Active export type.
	 */
	private function render_filter_field_provider_groups( string $active_export_type ): void {
		foreach ( $this->filter_field_providers->get_providers( $active_export_type ) as $provider ) {
			ob_start();
			$provider->render( $provider->get_default_selection() );
			$rows = (string) ob_get_clean();
			$rows = str_replace(
				'<tr',
				'<tr data-storeaccountant-export-filter-group="1" data-storeaccountant-export-type="' . esc_attr( $active_export_type ) . '"',
				$rows
			);

				echo wp_kses( $rows, $this->get_allowed_filter_row_html() );
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
