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

namespace StoreAccountant\Customer\Admin;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Filter\CustomerDateFilter;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use function is_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the customer date filter fields.
 */
final readonly class CustomerDateFilterFieldProvider implements ExportFilterFieldProviderInterface, HookRegistrarInterface {
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
		return CustomerDateFilter::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( string $export_type ): bool {
		return CustomerExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( ?ExportFilterSelection $selection = null, bool $read_only = false, bool $allow_concrete_months = true ): void {
		$period_selection = [];

		if ( null !== $selection ) {
			$period_selection = isset( $selection->settings['period'] ) && is_array( $selection->settings['period'] ) ? $selection->settings['period'] : [];
		}

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

		return new ExportFilterSelection(
			$this->get_id(),
			[
				'date_field'      => CustomerDateFilter::FIELD_DATE_CREATED,
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
				'date_field'      => CustomerDateFilter::FIELD_DATE_CREATED,
				'period_provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'period'          => [
					'provider' => MonthYearPeriodProvider::PROVIDER_ID,
					'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
				],
			]
		);
	}
}
