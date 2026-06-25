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

use WP_Post;
use StoreAccountant\Export\Admin\ExportSettingsFields;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\I18n;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use function is_array;
use function json_decode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the reusable export configuration form.
 */
final readonly class ExportConfigurationPageForm {
	/**
	 * Initializes the form.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportAdapterRegistry                        $export_adapters         Export adapter registry.
	 * @param ExportFilterFieldProviderRegistry            $filter_field_providers Export filter field providers.
	 * @param ExportFilterSelectionSerializer              $filter_serializer      Filter selection serializer.
	 * @param ExportSettingsFields                         $settings_fields       Shared export settings fields.
	 * @param DownloadPasswordManager                      $passwords               Download password manager.
	 * @param PermissionChecker                            $permissions             Permission checker.
	 */
	public function __construct(
		private ExportAdapterRegistry $export_adapters,
		private ExportFilterFieldProviderRegistry $filter_field_providers,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportSettingsFields $settings_fields,
		private DownloadPasswordManager $passwords,
		private PermissionChecker $permissions
	) {}

	/**
	 * Renders the export configuration form.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param WP_Post|null $configuration Current configuration.
	 */
	public function render( ?WP_Post $configuration = null, bool $read_only = false ): void {
		$export_adapters   = $this->export_adapters->get_all();
		$is_editing        = null !== $configuration;
		$submit_disabled   = [] === $export_adapters || ! $this->settings_fields->has_required_choices();
		$title             = $is_editing ? get_the_title( $configuration ) : '';
		$filter_selections = $is_editing ? $this->get_filter_selections( $configuration->ID ) : [];
		$export_adapter    = $is_editing ? $this->get_export_adapter( $configuration->ID ) : OrderExportAdapter::ADAPTER_ID;
		$tax_provider_id   = $is_editing ? $this->get_order_tax_field_provider( $configuration->ID ) : ExtendedOrderTaxFieldProvider::PROVIDER_ID;
		$export_writer     = $is_editing ? $this->get_export_writer( $configuration->ID ) : CsvExportRenderer::RENDERER_ID;
		$storage_engine    = $is_editing ? (string) get_post_meta( $configuration->ID, ExportConfigurationPostType::META_STORAGE_ENGINE, true ) : '';
		$batch_size        = $is_editing ? $this->get_batch_size( $configuration->ID ) : ExportPostType::DEFAULT_BATCH_SIZE;
		$settings          = $is_editing ? $this->get_additional_settings( $configuration->ID ) : [];
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="storeaccountant_save_export_configuration" />
			<?php if ( $is_editing && ! $read_only ) : ?>
				<input type="hidden" name="storeaccountant_export_configuration_id" value="<?php echo esc_attr( (string) $configuration->ID ); ?>" />
			<?php endif; ?>
			<?php if ( ! $read_only ) : ?>
				<?php wp_nonce_field( 'storeaccountant_save_export_configuration', 'storeaccountant_export_configuration_nonce' ); ?>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="storeaccountant-export-configuration-title"><?php esc_html_e( 'Configuration Title', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="storeaccountant-export-configuration-title"
								name="storeaccountant_export_configuration_title"
								class="regular-text"
								value="<?php echo esc_attr( $title ); ?>"
								required="required"
								<?php disabled( $read_only ); ?>
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="storeaccountant-export-adapter"><?php esc_html_e( 'Export Type', 'storeaccountant' ); ?></label>
						</th>
						<td>
							<?php if ( [] === $export_adapters ) : ?>
								<p class="description"><?php esc_html_e( 'No export adapters are available. Register at least one export adapter before saving a configuration.', 'storeaccountant' ); ?></p>
							<?php else : ?>
								<?php if ( $is_editing && ! $read_only ) : ?>
									<input type="hidden" name="storeaccountant_export_adapter" value="<?php echo esc_attr( $export_adapter ); ?>" />
								<?php endif; ?>
								<select id="storeaccountant-export-adapter" name="<?php echo esc_attr( 'storeaccountant_export_adapter' . ( $is_editing ? '_display' : '' ) ); ?>" required="required" <?php disabled( $is_editing || $read_only ); ?>>
									<?php foreach ( $export_adapters as $adapter ) : ?>
									<option value="<?php echo esc_attr( $adapter->get_id() ); ?>" <?php selected( $export_adapter, $adapter->get_id() ); ?>>
										<?php
											echo esc_html( I18n::translate_registry_label( 'export_adapter_', $adapter->get_id() ) );
										?>
									</option>
									<?php endforeach; ?>
								</select>
								<?php if ( $is_editing && ! $read_only ) : ?>
									<p class="description"><?php esc_html_e( 'The export adapter cannot be changed after the configuration has been created.', 'storeaccountant' ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( $is_editing ) : ?>
						<?php $this->render_filter_field_provider_groups( $export_adapter, $filter_selections, $read_only ); ?>
					<?php endif; ?>
					<?php if ( $is_editing ) : ?>
						<?php $this->settings_fields->render( $export_adapter, $export_writer, $storage_engine, $batch_size, $tax_provider_id, $settings, $read_only, false ); ?>
						<?php $this->render_download_password_row( $configuration->ID, $read_only ); ?>
						<?php $this->settings_fields->render_batch_size_row( $batch_size, $read_only ); ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! $read_only ) : ?>
				<?php submit_button( $is_editing ? __( 'Update Configuration', 'storeaccountant' ) : __( 'Save Configuration', 'storeaccountant' ), 'primary', 'storeaccountant_save_export_configuration', false, $submit_disabled ? [ 'disabled' => 'disabled' ] : [] ); ?>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Renders the configuration download password field.
	 */
	private function render_download_password_row( int $configuration_id, bool $read_only ): void {
		$has_password = $this->passwords->has_configuration_password( $configuration_id );
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-configuration-download-password"><?php esc_html_e( 'Download password', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( ! $this->passwords->is_available() ) : ?>
					<p class="description"><?php esc_html_e( 'Download passwords are unavailable because this server provides neither Sodium nor OpenSSL encryption.', 'storeaccountant' ); ?></p>
				<?php elseif ( $read_only ) : ?>
					<?php if ( $has_password && $this->permissions->can( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS, $configuration_id ) ) : ?>
						<?php $password = $this->passwords->reveal_configuration_password( $configuration_id ); ?>
						<?php if ( ! is_wp_error( $password ) ) : ?>
							<input type="text" class="regular-text" value="<?php echo esc_attr( $password ); ?>" readonly="readonly" />
						<?php endif; ?>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'A download password is stored for this configuration.', 'storeaccountant' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<input
						type="password"
						id="storeaccountant-configuration-download-password"
						name="storeaccountant_configuration_download_password"
						class="regular-text"
						value=""
						autocomplete="new-password"
					/>
					<p class="description"><?php esc_html_e( 'Enter a download password for this configuration. Leave empty to store the current global download password.', 'storeaccountant' ); ?></p>
					<?php if ( $has_password && $this->permissions->can( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS, $configuration_id ) ) : ?>
						<?php $password = $this->passwords->reveal_configuration_password( $configuration_id ); ?>
						<?php if ( ! is_wp_error( $password ) ) : ?>
							<p>
								<label><?php esc_html_e( 'Current Download Password', 'storeaccountant' ); ?></label><br />
								<input type="text" class="regular-text" value="<?php echo esc_attr( $password ); ?>" readonly="readonly" />
							</p>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets the stored export adapter.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_export_adapter( int $post_id ): string {
		$adapter_id = (string) get_post_meta( $post_id, ExportConfigurationPostType::META_EXPORT_ADAPTER, true );

		if ( '' !== $adapter_id && null !== $this->export_adapters->get( $adapter_id ) ) {
			return $adapter_id;
		}

		return OrderExportAdapter::ADAPTER_ID;
	}

	/**
	 * Renders filter field groups for the configured export adapters.
	 *
	 * @param string                                                              $active_export_type Active export type.
	 * @param array<string, \StoreAccountant\Export\Filter\ExportFilterSelection> $filter_selections Stored selections.
	 */
	private function render_filter_field_provider_groups( string $active_export_type, array $filter_selections, bool $read_only = false ): void {
		foreach ( $this->export_adapters->get_all() as $adapter ) {
			$export_type = $adapter->get_id();

			if ( $active_export_type !== $export_type ) {
				continue;
			}

			foreach ( $this->filter_field_providers->get_providers( $export_type ) as $provider ) {
				ob_start();
				$selection = $filter_selections[ $provider->get_id() ] ?? $provider->get_default_selection();

				if ( $provider instanceof OrderDateFilterFieldProvider || $provider instanceof CustomerDateFilterFieldProvider ) {
					$provider->render( $selection, $read_only, false );
				} else {
					$provider->render( $selection, $read_only );
				}

				$rows = (string) ob_get_clean();

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
			'disabled'         => true,
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

	/**
	 * Gets the stored export writer.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_export_writer( int $post_id ): string {
		$writer_id = (string) get_post_meta( $post_id, ExportConfigurationPostType::META_EXPORT_WRITER, true );

		if ( '' !== $writer_id ) {
			return $writer_id;
		}

		return CsvExportRenderer::RENDERER_ID;
	}

	/**
	 * Gets the stored batch size.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_batch_size( int $post_id ): int {
		$batch_size = (int) get_post_meta( $post_id, ExportConfigurationPostType::META_BATCH_SIZE, true );

		return $batch_size >= ExportPostType::MIN_BATCH_SIZE ? $batch_size : ExportPostType::DEFAULT_BATCH_SIZE;
	}

	/**
	 * Gets the stored order tax field provider.
	 *
	 * @param int $post_id Configuration post ID.
	 */
	private function get_order_tax_field_provider( int $post_id ): string {
		return $this->settings_fields->get_tax_provider_id_from_configuration( $post_id );
	}

	/**
	 * Gets stored filter selections keyed by filter ID.
	 *
	 * @param int $post_id Configuration post ID.
	 *
	 * @return array<string, \StoreAccountant\Export\Filter\ExportFilterSelection>
	 */
	private function get_filter_selections( int $post_id ): array {
		$selections = [];

		foreach ( $this->filter_serializer->decode( (string) get_post_meta( $post_id, ExportConfigurationPostType::META_FILTERS, true ) ) as $selection ) {
			$selections[ $selection->filter_id ] = $selection;
		}

		return $selections;
	}

	/**
	 * Gets stored additional provider settings.
	 *
	 * @param int $post_id Configuration post ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_additional_settings( int $post_id ): array {
		$settings = json_decode( (string) get_post_meta( $post_id, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, true ), true );

		return is_array( $settings ) ? $settings : [];
	}
}
