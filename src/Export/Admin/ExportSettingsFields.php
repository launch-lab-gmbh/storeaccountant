<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Export\Admin;

use WP_Error;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Contract\ExportTypeAwareInterface;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\I18n;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use function __;
use function absint;
use function add_query_arg;
use function admin_url;
use function disabled;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function esc_url;
use function is_array;
use function is_numeric;
use function is_wp_error;
use function selected;
use function wp_unslash;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes shared export settings fields.
 */
final readonly class ExportSettingsFields {
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private ExportRendererRegistry $export_writers,
		private ExportConfigurationFormFieldProviderRegistry $field_providers,
		private OrderTaxFieldProviderField $tax_field_provider_field
	) {}

	/**
	 * Checks whether the settings field choices required to submit are available.
	 */
	public function has_required_choices(): bool {
		return [] !== $this->storage_adapters->get_enabled() && [] !== $this->export_writers->get_all();
	}

	/**
	 * Renders shared export settings rows.
	 *
	 * @param string               $export_type         Export adapter identifier.
	 * @param string               $export_writer       Selected export renderer.
	 * @param string               $storage_engine      Selected storage adapter.
	 * @param int                  $batch_size          Selected batch size.
	 * @param string               $tax_provider_id     Selected order tax field provider.
	 * @param array<string, mixed> $additional_settings Additional provider settings.
	 * @param string               $empty_state_context Empty state message context.
	 */
	public function render(
		string $export_type,
		string $export_writer = CsvExportRenderer::RENDERER_ID,
		string $storage_engine = '',
		int $batch_size = ExportPostType::DEFAULT_BATCH_SIZE,
		string $tax_provider_id = ExtendedOrderTaxFieldProvider::PROVIDER_ID,
		array $additional_settings = [],
		bool $read_only = false,
		bool $include_batch_size = true,
		string $empty_state_context = 'configuration'
	): void {
		$this->render_export_writer_row( $export_writer, $read_only, $empty_state_context );
		$this->render_storage_engine_row( $storage_engine, $read_only, $empty_state_context );

		if ( OrderExportAdapter::ADAPTER_ID === $export_type ) {
			$this->tax_field_provider_field->render( $tax_provider_id, $read_only );
		}

		foreach ( $this->get_supported_field_providers( $export_type ) as $provider ) {
			$provider->render_fields(
				is_array( $additional_settings[ $provider->get_id() ] ?? null ) ? $additional_settings[ $provider->get_id() ] : [],
				$read_only
			);
		}

		if ( $include_batch_size ) {
			$this->render_batch_size_row( $batch_size, $read_only );
		}
	}

	/**
	 * Gets a valid order tax field provider ID from request data.
	 *
	 * @param string               $export_type Export adapter identifier.
	 * @param array<string, mixed> $request     Request data.
	 */
	public function get_tax_provider_id_from_request( string $export_type, array $request ): string {
		if ( OrderExportAdapter::ADAPTER_ID !== $export_type ) {
			return ExtendedOrderTaxFieldProvider::PROVIDER_ID;
		}

		return $this->tax_field_provider_field->get_provider_id_from_request( $request );
	}

	/**
	 * Gets a valid order tax field provider ID from stored configuration metadata.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	public function get_tax_provider_id_from_configuration( int $configuration_id ): string {
		return $this->tax_field_provider_field->get_provider_id_from_configuration( $configuration_id );
	}

	/**
	 * Gets and validates additional provider settings.
	 *
	 * @param string               $export_type Export adapter identifier.
	 * @param array<string, mixed> $request     Request data.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_additional_settings_from_request( string $export_type, array $request ): array|WP_Error {
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
	 * Gets the batch size from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function get_batch_size_from_request( array $request ): int|WP_Error {
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
	 * Renders the export writer field.
	 */
	private function render_export_writer_row( string $export_writer, bool $read_only, string $empty_state_context ): void {
		$export_writers = $this->export_writers->get_all();
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-export-writer"><?php esc_html_e( 'Export Format', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( [] === $export_writers ) : ?>
					<?php if ( 'export' === $empty_state_context ) : ?>
						<p class="description"><?php esc_html_e( 'No export formats are available. Register at least one export renderer before starting an export.', 'storeaccountant' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No export formats are available. Register at least one export renderer before saving a configuration.', 'storeaccountant' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<select id="storeaccountant-export-writer" name="storeaccountant_export_writer" required="required" <?php disabled( $read_only ); ?>>
						<?php foreach ( $export_writers as $writer ) : ?>
						<option value="<?php echo esc_attr( $writer->get_id() ); ?>" <?php selected( $export_writer, $writer->get_id() ); ?>>
							<?php echo esc_html( I18n::translate_registry_label( 'exporter_', $writer->get_id() ) ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the storage adapter field.
	 */
	private function render_storage_engine_row( string $storage_engine, bool $read_only, string $empty_state_context ): void {
		$storage_adapters = $this->storage_adapters->get_enabled();
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-storage-engine"><?php esc_html_e( 'Storage Location', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<?php if ( [] === $storage_adapters ) : ?>
					<?php if ( 'export' === $empty_state_context ) : ?>
						<p class="description"><?php esc_html_e( 'No storage locations are enabled. Enable at least one storage location before starting an export.', 'storeaccountant' ); ?></p>
					<?php else : ?>
						<div class="notice notice-error inline">
							<p>
								<?php esc_html_e( 'No storage locations are enabled. Enable at least one storage location before saving a configuration.', 'storeaccountant' ); ?>
								<a href="<?php echo esc_url( $this->get_storage_settings_url() ); ?>">
									<?php esc_html_e( 'Open storage settings', 'storeaccountant' ); ?>
								</a>
							</p>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<select id="storeaccountant-storage-engine" name="storeaccountant_storage_engine" required="required" <?php disabled( $read_only ); ?>>
						<?php foreach ( $storage_adapters as $storage_adapter ) : ?>
						<option value="<?php echo esc_attr( $storage_adapter->get_id() ); ?>" <?php selected( $storage_engine, $storage_adapter->get_id() ); ?>>
							<?php echo esc_html( I18n::translate_registry_label( 'storage_adapter_', $storage_adapter->get_id() ) ); ?>
						</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders the batch size field.
	 */
	public function render_batch_size_row( int $batch_size, bool $read_only = false ): void {
		?>
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
					value="<?php echo esc_attr( (string) $batch_size ); ?>"
					min="<?php echo esc_attr( (string) ExportPostType::MIN_BATCH_SIZE ); ?>"
					step="1"
					required="required"
					<?php disabled( $read_only ); ?>
				/>
				<p class="description"><?php esc_html_e( 'StoreAccountant automatically splits the export into batches. This value defines how many items are exported in each batch; it does not limit the total number of exported items. The minimum is 10. If you are unsure, leave it at 100.', 'storeaccountant' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets additional form field providers that support the selected export type.
	 *
	 * @param string $export_type Export adapter identifier.
	 *
	 * @return array<int, \StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface>
	 */
	private function get_supported_field_providers( string $export_type ): array {
		$providers = [];

		foreach ( $this->field_providers->get_all() as $provider ) {
			if ( ! $provider instanceof ExportTypeAwareInterface || $provider->supports_export_type( $export_type ) ) {
				$providers[] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * Gets the storage settings URL.
	 */
	private function get_storage_settings_url(): string {
		return add_query_arg(
			[
				'page' => 'storeaccountant-settings',
				'tab'  => 'storage-locations',
			],
			admin_url( 'admin.php' )
		);
	}
}
