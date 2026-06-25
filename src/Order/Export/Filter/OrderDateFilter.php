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

namespace StoreAccountant\Order\Export\Filter;

use DateTimeImmutable;
use DateTimeZone;
use WC_Order_Query;
use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use function in_array;
use function is_array;
use function is_scalar;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters WooCommerce orders by a selectable date property and period.
 */
final readonly class OrderDateFilter implements ExportFilterInterface, HookRegistrarInterface {
	public const FILTER_ID            = 'order_date';
	public const FIELD_DATE_CREATED   = 'date_created';
	public const FIELD_DATE_MODIFIED  = 'date_modified';
	public const FIELD_DATE_COMPLETED = 'date_completed';
	public const FIELD_DATE_PAID      = 'date_paid';

	/**
	 * Initializes the order date filter.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param PeriodProviderRegistry $period_providers Period provider registry.
	 */
	public function __construct(
		private PeriodProviderRegistry $period_providers
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
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
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( string $export_type ): bool {
		return OrderExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error {
		if ( ! $query instanceof WC_Order_Query ) {
			return new WP_Error( 'storeaccountant_invalid_order_query', __( 'The order date filter requires a WooCommerce order query.', 'storeaccountant' ) );
		}

		$date_field      = $this->get_date_field( $selection->settings['date_field'] ?? self::FIELD_DATE_CREATED );
		$resolved_period = $selection->settings['resolved_period'] ?? null;
		$period          = is_array( $resolved_period ) ? $this->get_period_from_snapshot( $resolved_period ) : null;

		if ( $this->is_all_time_period( $selection ) ) {
			return true;
		}

		if ( null === $period ) {
			$provider_id = isset( $selection->settings['period_provider'] ) && is_scalar( $selection->settings['period_provider'] )
				? sanitize_key( (string) $selection->settings['period_provider'] )
				: MonthYearPeriodProvider::PROVIDER_ID;
			$provider    = $this->period_providers->get( $provider_id );

			if ( null === $provider ) {
				return new WP_Error( 'storeaccountant_period_provider_unavailable', __( 'The configured period provider is unavailable.', 'storeaccountant' ) );
			}

			$period_selection = isset( $selection->settings['period'] ) && is_array( $selection->settings['period'] ) ? $selection->settings['period'] : [];
			$period           = $provider->resolve( $period_selection );
		}

		if ( is_wp_error( $period ) ) {
			return $period;
		}

		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $period->start_at, new DateTimeZone( 'UTC' ) );
		$end   = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $period->end_at, new DateTimeZone( 'UTC' ) );

		if ( false === $start || false === $end ) {
			return new WP_Error( 'storeaccountant_invalid_period', __( 'The selected export period is invalid.', 'storeaccountant' ) );
		}

		$query->set( $date_field, $start->getTimestamp() . '...' . $end->getTimestamp() );

		return true;
	}

	/**
	 * Gets supported date field labels.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, string>
	 */
	public static function get_date_fields(): array {
		return [
			self::FIELD_DATE_CREATED   => __( 'Created date', 'storeaccountant' ),
			self::FIELD_DATE_MODIFIED  => __( 'Modified date', 'storeaccountant' ),
			self::FIELD_DATE_COMPLETED => __( 'Completed date', 'storeaccountant' ),
			self::FIELD_DATE_PAID      => __( 'Paid date', 'storeaccountant' ),
		];
	}

	/**
	 * Sanitizes a requested date field.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $date_field Requested date field.
	 */
	public static function get_date_field( mixed $date_field ): string {
		$date_field = is_scalar( $date_field ) ? sanitize_key( (string) $date_field ) : '';

		return in_array( $date_field, array_keys( self::get_date_fields() ), true ) ? $date_field : self::FIELD_DATE_CREATED;
	}

	/**
	 * Gets a period from a stored snapshot.
	 *
	 * @param array<string, mixed> $snapshot Stored period snapshot.
	 */
	private function get_period_from_snapshot( array $snapshot ): ?\StoreAccountant\Export\ExportPeriod {
		$start_at = isset( $snapshot['start_at'] ) && is_scalar( $snapshot['start_at'] ) ? (string) $snapshot['start_at'] : '';
		$end_at   = isset( $snapshot['end_at'] ) && is_scalar( $snapshot['end_at'] ) ? (string) $snapshot['end_at'] : '';

		if ( '' === $start_at || '' === $end_at ) {
			return null;
		}

		return new \StoreAccountant\Export\ExportPeriod( $start_at, $end_at );
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
