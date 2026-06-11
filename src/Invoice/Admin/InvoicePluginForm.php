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

namespace StoreAccountant\Invoice\Admin;

use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders invoice plugin settings.
 */
final readonly class InvoicePluginForm {
	/**
	 * Initializes the form.
	 *
	 * @param InvoicePluginRegistry $invoice_plugins Invoice plugin registry.
	 */
	public function __construct(
		private InvoicePluginRegistry $invoice_plugins
	) {}

	/**
	 * Renders the invoice plugin fields.
	 */
	public function render_fields(): void {
		$available = $this->invoice_plugins->get_available();
		?>
		<?php if ( [] === $available ) : ?>
			<tr>
				<td colspan="2">
					<p class="description"><?php esc_html_e( 'No supported invoice plugin is currently active.', 'storeaccountant' ); ?></p>
				</td>
			</tr>
			<?php return; ?>
		<?php endif; ?>

		<?php foreach ( $available as $invoice_plugin ) : ?>
			<tr>
				<th scope="row">
					<?php
						echo esc_html( I18n::translate_registry_label( 'invoice_plugin_', $invoice_plugin->get_id() ) );
					?>
				</th>
				<td>
					<label for="storeaccountant-invoice-plugin-<?php echo esc_attr( $invoice_plugin->get_id() ); ?>">
						<input
							type="checkbox"
							class="storeaccountant-invoice-provider-checkbox"
							id="storeaccountant-invoice-plugin-<?php echo esc_attr( $invoice_plugin->get_id() ); ?>"
							name="storeaccountant_enabled_invoice_plugin"
							value="<?php echo esc_attr( $invoice_plugin->get_id() ); ?>"
							<?php checked( $this->invoice_plugins->is_enabled( $invoice_plugin->get_id() ) ); ?>
						/>
						<?php esc_html_e( 'Enable this invoice provider for export fields.', 'storeaccountant' ); ?>
					</label>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td colspan="2">
				<p class="description"><?php esc_html_e( 'Only one invoice provider can be enabled at a time.', 'storeaccountant' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
