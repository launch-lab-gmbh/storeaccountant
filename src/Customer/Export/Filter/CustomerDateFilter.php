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

namespace StoreAccountant\Customer\Export\Filter;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use function is_array;
use function is_scalar;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters WooCommerce customers by a selectable date property and period.
 */
final readonly class CustomerDateFilter implements ExportFilterInterface, HookRegistrarInterface {
	public const FILTER_ID          = 'customer_date';
	public const FIELD_DATE_CREATED = CustomerQueryCriteria::DATE_FIELD_CREATED;

	/**
	 * Initializes the customer date filter.
	 *
	 * @param PeriodProviderRegistry $period_providers Period provider registry.
	 */
	public function __construct(
		private PeriodProviderRegistry $period_providers
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter',
			function ( array $filters ): array {
				$filters[ self::FILTER_ID ] = $this;

				return $filters;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::FILTER_ID;
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
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error {
		if ( ! $query instanceof CustomerQueryCriteria ) {
			return new WP_Error( 'storeaccountant_invalid_customer_query', __( 'The customer date filter requires a WooCommerce customer query.', 'storeaccountant' ) );
		}

		if ( $this->is_all_time_period( $selection ) ) {
			return true;
		}

		$period = $this->get_period( $selection );

		if ( is_wp_error( $period ) ) {
			return $period;
		}

		$query->date_field = self::FIELD_DATE_CREATED;
		$query->period     = $period;

		return true;
	}

	/**
	 * Resolves the selected export period.
	 *
	 * @param ExportFilterSelection $selection Filter selection.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	private function get_period( ExportFilterSelection $selection ): ExportPeriod|WP_Error {
		$resolved_period = $selection->settings['resolved_period'] ?? null;
		$period          = is_array( $resolved_period ) ? $this->get_period_from_snapshot( $resolved_period ) : null;

		if ( null !== $period ) {
			return $period;
		}

		$provider_id = isset( $selection->settings['period_provider'] ) && is_scalar( $selection->settings['period_provider'] )
			? sanitize_key( (string) $selection->settings['period_provider'] )
			: MonthYearPeriodProvider::PROVIDER_ID;
		$provider    = $this->period_providers->get( $provider_id );

		if ( null === $provider ) {
			return new WP_Error( 'storeaccountant_period_provider_unavailable', __( 'The configured period provider is unavailable.', 'storeaccountant' ) );
		}

		$period_selection = isset( $selection->settings['period'] ) && is_array( $selection->settings['period'] ) ? $selection->settings['period'] : [];

		return $provider->resolve( $period_selection );
	}

	/**
	 * Gets a period from a stored snapshot.
	 *
	 * @param array<string, mixed> $snapshot Stored period snapshot.
	 */
	private function get_period_from_snapshot( array $snapshot ): ?ExportPeriod {
		$start_at = isset( $snapshot['start_at'] ) && is_scalar( $snapshot['start_at'] ) ? (string) $snapshot['start_at'] : '';
		$end_at   = isset( $snapshot['end_at'] ) && is_scalar( $snapshot['end_at'] ) ? (string) $snapshot['end_at'] : '';

		if ( '' === $start_at || '' === $end_at ) {
			return null;
		}

		return new ExportPeriod( $start_at, $end_at );
	}

	/**
	 * Checks whether the selected period intentionally leaves dates unrestricted.
	 *
	 * @param ExportFilterSelection $selection Filter selection.
	 */
	private function is_all_time_period( ExportFilterSelection $selection ): bool {
		$period_selection = $selection->settings['period'] ?? null;

		if ( ! is_array( $period_selection ) || ! isset( $period_selection['month'] ) || ! is_scalar( $period_selection['month'] ) ) {
			return false;
		}

		return MonthYearPeriodProvider::PERIOD_ALL_TIME === sanitize_key( (string) $period_selection['month'] );
	}
}
