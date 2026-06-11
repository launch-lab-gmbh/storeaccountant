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

namespace StoreAccountant\Order\Admin;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\Filter\OrderDateFilter;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use function is_array;
use function is_scalar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the order date filter fields.
 */
final readonly class OrderDateFilterFieldProvider implements ExportFilterFieldProviderInterface, HookRegistrarInterface {
	public const FIELD_DATE_FIELD = 'storeaccountant_order_date_field';

	/**
	 * Initializes the field provider.
	 *
	 * @param MonthYearExportPeriodFieldProvider $period_field_provider Period field provider.
	 */
	public function __construct(
		private MonthYearExportPeriodFieldProvider $period_field_provider
	) {}

	/**
	 * {@inheritDoc}
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
	 */
	public function get_id(): string {
		return OrderDateFilter::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false, bool $allow_concrete_months = true ): void {
		$date_field       = OrderDateFilter::FIELD_DATE_CREATED;
		$period_selection = [];

		if ( null !== $selection ) {
			$date_field       = OrderDateFilter::get_date_field( $selection->settings['date_field'] ?? OrderDateFilter::FIELD_DATE_CREATED );
			$period_selection = isset( $selection->settings['period'] ) && is_array( $selection->settings['period'] ) ? $selection->settings['period'] : [];
		}
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-order-date-field"><?php esc_html_e( 'Order Date Field', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select id="storeaccountant-order-date-field" name="<?php echo esc_attr( self::FIELD_DATE_FIELD ); ?>" <?php disabled( $read_only ); ?>>
					<?php foreach ( OrderDateFilter::get_date_fields() as $field_id => $label ) : ?>
						<option value="<?php echo esc_attr( $field_id ); ?>" <?php selected( $date_field, $field_id ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The selected period is applied to this WooCommerce order date property.', 'storeaccountant' ); ?></p>
			</td>
		</tr>
		<?php
		$this->period_field_provider->render( null, $period_selection, $read_only, $allow_concrete_months );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_selection_from_request( array $request ): ExportFilterSelection|WP_Error {
		$period_selection = $this->period_field_provider->get_period_selection_from_request( $request );
		$period           = $this->period_field_provider->get_period_from_selection( $period_selection );

		if ( is_wp_error( $period ) ) {
			return $period;
		}

		$date_field = isset( $request[ self::FIELD_DATE_FIELD ] ) && is_scalar( $request[ self::FIELD_DATE_FIELD ] )
			? OrderDateFilter::get_date_field( wp_unslash( $request[ self::FIELD_DATE_FIELD ] ) )
			: OrderDateFilter::FIELD_DATE_CREATED;

		return new ExportFilterSelection(
			$this->get_id(),
			[
				'date_field'      => $date_field,
				'period_provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'period'          => $period_selection,
			]
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_selection(): ExportFilterSelection {
		return new ExportFilterSelection(
			$this->get_id(),
			[
				'date_field'      => OrderDateFilter::FIELD_DATE_CREATED,
				'period_provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'period'          => [
					'provider' => MonthYearPeriodProvider::PROVIDER_ID,
					'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
				],
			]
		);
	}
}
