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

namespace StoreAccountant\Product\Admin;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Filter\ProductVariantExportFilter;
use function add_filter;
use function disabled;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function is_scalar;
use function selected;
use function wp_unslash;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the product variant export setting.
 */
final readonly class ProductVariantExportFieldProvider implements ExportFilterFieldProviderInterface, HookRegistrarInterface {
	public const FIELD_VARIANT_MODE = 'storeaccountant_product_variant_mode';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter_field_provider',
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
		return ProductVariantExportFilter::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( string $export_type ): bool {
		return ProductExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false ): void {
		$mode = ProductVariantExportFilter::MODE_PARENT_PRODUCTS;

		if ( null !== $selection ) {
			$mode = ProductVariantExportFilter::sanitize_mode( $selection->settings['mode'] ?? ProductVariantExportFilter::MODE_PARENT_PRODUCTS );
		}
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-product-variant-mode"><?php esc_html_e( 'Product Variants', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select id="storeaccountant-product-variant-mode" name="<?php echo esc_attr( self::FIELD_VARIANT_MODE ); ?>" <?php disabled( $read_only ); ?>>
					<?php foreach ( ProductVariantExportFilter::get_modes() as $mode_id => $label ) : ?>
						<option value="<?php echo esc_attr( $mode_id ); ?>" <?php selected( $mode, $mode_id ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Choose whether variable product variants should be included as separate export rows.', 'storeaccountant' ); ?></p>
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
	public function get_selection_from_request( array $request ): ExportFilterSelection|WP_Error {
		$mode = isset( $request[ self::FIELD_VARIANT_MODE ] ) && is_scalar( $request[ self::FIELD_VARIANT_MODE ] )
			? ProductVariantExportFilter::sanitize_mode( wp_unslash( $request[ self::FIELD_VARIANT_MODE ] ) )
			: ProductVariantExportFilter::MODE_PARENT_PRODUCTS;

		return new ExportFilterSelection(
			$this->get_id(),
			[
				'mode' => $mode,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_default_selection(): ExportFilterSelection {
		return new ExportFilterSelection(
			$this->get_id(),
			[
				'mode' => ProductVariantExportFilter::MODE_PARENT_PRODUCTS,
			]
		);
	}
}
