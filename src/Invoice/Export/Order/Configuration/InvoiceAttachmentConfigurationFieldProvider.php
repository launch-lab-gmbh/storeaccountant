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

namespace StoreAccountant\Invoice\Export\Order\Configuration;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface;
use StoreAccountant\Export\Contract\ExportTypeAwareInterface;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function sprintf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds invoice attachment settings to order export configurations.
 */
final readonly class InvoiceAttachmentConfigurationFieldProvider implements ExportConfigurationFormFieldProviderInterface, ExportTypeAwareInterface, HookRegistrarInterface {
	/**
	 * Initializes the provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param InvoicePluginDetector $detector Invoice plugin detector.
	 */
	public function __construct(
		private InvoicePluginDetector $detector
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_configuration_form_field_provider',
			function ( array $providers ): array {
				$providers[ $this->get_id() ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return InvoiceExportAttachmentSettings::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports_export_type( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_fields( array $settings, bool $read_only = false ): void {
		$plugin = $this->detector->get_enabled();

		if ( null === $plugin || [] === $plugin->get_invoice_file_types() ) {
			return;
		}

		$export_files       = true === ( $settings[ InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES ] ?? false );
		$file_types         = $settings[ InvoiceExportAttachmentSettings::OPTION_FILE_TYPES ] ?? [];
		$file_types         = is_array( $file_types ) ? array_values( $file_types ) : [];
		$all_types_selected = $export_files && [] === $file_types;
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Invoice Files', 'storeaccountant' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><?php esc_html_e( 'Invoice file types', 'storeaccountant' ); ?></legend>
					<?php foreach ( $plugin->get_invoice_file_types() as $type ) : ?>
						<label for="storeaccountant-invoice-file-type-<?php echo esc_attr( $type->id ); ?>" style="display:block;margin-top:0;">
							<input
								type="checkbox"
								id="storeaccountant-invoice-file-type-<?php echo esc_attr( $type->id ); ?>"
								name="storeaccountant_invoice_file_types[]"
								value="<?php echo esc_attr( $type->id ); ?>"
								<?php checked( $all_types_selected || in_array( $type->id, $file_types, true ) ); ?>
								<?php disabled( $read_only ); ?>
							/>
							<?php echo esc_html( $this->get_file_type_label( $type->id, $type->label ) ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Selected invoice files are stored in the export ZIP and listed by file name in the export rows.', 'storeaccountant' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function sanitize_settings( array $request ): array {
		$file_types = isset( $request['storeaccountant_invoice_file_types'] ) && is_array( $request['storeaccountant_invoice_file_types'] )
			? array_map( 'sanitize_key', wp_unslash( $request['storeaccountant_invoice_file_types'] ) )
			: [];

		return [
			InvoiceExportAttachmentSettings::OPTION_EXPORT_FILES => [] !== $file_types,
			InvoiceExportAttachmentSettings::OPTION_FILE_TYPES   => array_values( $file_types ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function validate_settings( array $settings ): true|WP_Error {
		return true;
	}

	/**
	 * Gets the checkbox label for an invoice file type.
	 *
	 * @param string $type_id Type ID.
	 * @param string $label   Type label.
	 */
	private function get_file_type_label( string $type_id, string $label ): string {
		return match ( $type_id ) {
			'pdf'   => __( 'Export PDF invoice as attachment.', 'storeaccountant' ),
			'xml'   => __( 'Export e-invoice as attachment.', 'storeaccountant' ),
			default => sprintf(
				/* translators: %s: Invoice file type label. */
				__( 'Export %s as attachment.', 'storeaccountant' ),
				$label
			),
		};
	}
}
