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

namespace StoreAccountant\Export\Filter\Period;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Period\Contract\PeriodProviderInterface;
use function ctype_digit;
use function in_array;
use function is_scalar;
use function sprintf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves month/year period selections for export filters.
 */
final readonly class MonthYearPeriodProvider implements PeriodProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID          = 'month-year';
	public const PERIOD_ALL_TIME      = 'all_time';
	public const PERIOD_CURRENT_MONTH = 'current_month';
	public const PERIOD_LAST_MONTH    = 'last_month';

	private const PAST_YEAR_COUNT = 10;

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter_period_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

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
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function resolve( array $selection ): ExportPeriod|WP_Error {
		$month_value = isset( $selection['month'] ) && is_scalar( $selection['month'] ) ? sanitize_key( (string) $selection['month'] ) : '';
		$timezone    = wp_timezone();

		if ( self::PERIOD_ALL_TIME === $month_value ) {
			return $this->get_all_time_period( $timezone );
		}

		if ( in_array( $month_value, [ self::PERIOD_CURRENT_MONTH, self::PERIOD_LAST_MONTH ], true ) ) {
			return $this->get_relative_period( $month_value, $timezone );
		}

		if ( ! ctype_digit( $month_value ) ) {
			return $this->get_invalid_period_error();
		}

		$month        = (int) $month_value;
		$year         = isset( $selection['year'] ) ? absint( $selection['year'] ) : 0;
		$current_year = (int) current_time( 'Y' );

		if ( $month < 1 || $month > 12 || $year < $current_year - self::PAST_YEAR_COUNT || $year > $current_year ) {
			return $this->get_invalid_period_error();
		}

		$start = DateTimeImmutable::createFromFormat( '!Y-n-j H:i:s', sprintf( '%d-%d-1 00:00:00', $year, $month ), $timezone );

		if ( false === $start ) {
			return $this->get_invalid_period_error();
		}

		$current_month_start = new DateTimeImmutable( 'first day of this month 00:00:00', $timezone );

		if ( $start > $current_month_start ) {
			return $this->get_invalid_period_error();
		}

		return $this->get_period_from_start( $start );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function format_label( ExportPeriod $period ): string {
		$start = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $period->start_at, new DateTimeZone( 'UTC' ) );

		if ( false === $start ) {
			return '';
		}

		return wp_date( 'F Y', $start->setTimezone( wp_timezone() )->getTimestamp() );
	}

	/**
	 * Gets the complete selectable export period.
	 *
	 * @param DateTimeZone $timezone WordPress timezone.
	 */
	private function get_all_time_period( DateTimeZone $timezone ): ExportPeriod {
		$current_year = (int) current_time( 'Y' );
		$start        = DateTimeImmutable::createFromFormat( '!Y-n-j H:i:s', sprintf( '%d-1-1 00:00:00', $current_year - self::PAST_YEAR_COUNT ), $timezone );
		$end          = new DateTimeImmutable( 'now', $timezone );

		if ( false === $start ) {
			$start = $end->modify( '-' . self::PAST_YEAR_COUNT . ' years' )->setDate( $current_year - self::PAST_YEAR_COUNT, 1, 1 )->setTime( 0, 0, 0 );
		}

		return new ExportPeriod(
			$start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
		);
	}

	/**
	 * Gets a relative month period.
	 *
	 * @param string       $period   Relative period key.
	 * @param DateTimeZone $timezone WordPress timezone.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	private function get_relative_period( string $period, DateTimeZone $timezone ): ExportPeriod|WP_Error {
		$start = new DateTimeImmutable( 'first day of this month 00:00:00', $timezone );

		if ( self::PERIOD_LAST_MONTH === $period ) {
			$start = $start->modify( '-1 month' );
		}

		return $this->get_period_from_start( $start );
	}

	/**
	 * Gets an export period from a local month start.
	 *
	 * @param DateTimeImmutable $start Local month start.
	 */
	private function get_period_from_start( DateTimeImmutable $start ): ExportPeriod {
		$end = $start->modify( 'last day of this month' )->setTime( 23, 59, 59 );

		return new ExportPeriod(
			$start->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
			$end->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' )
		);
	}

	/**
	 * Gets the shared invalid period error.
	 */
	private function get_invalid_period_error(): WP_Error {
		return new WP_Error( 'storeaccountant_invalid_period', __( 'The selected export period is invalid.', 'storeaccountant' ) );
	}
}
