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

namespace StoreAccountant\Tax\Admin;

use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use function disabled;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the order tax field provider configuration field.
 */
final readonly class OrderTaxFieldProviderField {
	/**
	 * Initializes the order tax field provider field.
	 *
	 * @param OrderTaxFieldProviderRegistry $tax_field_providers Tax field provider registry.
	 */
	public function __construct(
		private OrderTaxFieldProviderRegistry $tax_field_providers
	) {}

	/**
	 * Renders the tax provider select field.
	 *
	 * @param string $selected_provider_id Selected provider ID.
	 */
	public function render( string $selected_provider_id, bool $read_only = false ): void {
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-order-tax-field-provider"><?php esc_html_e( 'Tax Fields', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select id="storeaccountant-order-tax-field-provider" name="storeaccountant_order_tax_field_provider" <?php disabled( $read_only ); ?>>
					<?php foreach ( $this->tax_field_providers->get_all() as $tax_provider ) : ?>
						<option value="<?php echo esc_attr( $tax_provider->get_id() ); ?>" <?php selected( $selected_provider_id, $tax_provider->get_id() ); ?>>
							<?php echo esc_html( $tax_provider->get_label() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets a valid provider ID from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function get_provider_id_from_request( array $request ): string {
		$provider_id = isset( $request['storeaccountant_order_tax_field_provider'] )
			? sanitize_key( wp_unslash( $request['storeaccountant_order_tax_field_provider'] ) )
			: ExtendedOrderTaxFieldProvider::PROVIDER_ID;

		return null !== $this->tax_field_providers->get( $provider_id ) ? $provider_id : '';
	}

	/**
	 * Gets a valid provider ID from stored configuration metadata.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	public function get_provider_id_from_configuration( int $configuration_id ): string {
		$provider_id = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER, true );

		if ( '' !== $provider_id && null !== $this->tax_field_providers->get( $provider_id ) ) {
			return $provider_id;
		}

		return ExtendedOrderTaxFieldProvider::PROVIDER_ID;
	}
}
