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

namespace StoreAccountant\Export\Admin\Period;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use function ctype_digit;
use function in_array;
use function is_scalar;
use function mktime;
use function range;
use function sprintf;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the free month/year period fields.
 */
final readonly class MonthYearExportPeriodFieldProvider {
	private const PAST_YEAR_COUNT      = 10;
	private const PERIOD_ALL_TIME      = MonthYearPeriodProvider::PERIOD_ALL_TIME;
	private const PERIOD_CURRENT_MONTH = 'current_month';
	private const PERIOD_LAST_MONTH    = 'last_month';

	/**
	 * Renders period fields.
	 *
	 * @param ExportPeriod|null    $period                Current period.
	 * @param array<string, mixed> $selection             Current stored selection.
	 * @param bool                 $read_only             Whether fields should be rendered read-only.
	 * @param bool                 $allow_concrete_months Whether concrete month/year selections are available.
	 */
	public function render( ?ExportPeriod $period = null, array $selection = [], bool $read_only = false, bool $allow_concrete_months = true ): void {
		$current_year    = (int) current_time( 'Y' );
		$selected_date   = $this->get_selected_date( $period );
		$selection_month = isset( $selection['month'] ) && is_scalar( $selection['month'] ) ? (string) $selection['month'] : '';
		$selection_year  = isset( $selection['year'] ) ? (int) $selection['year'] : 0;
		$selected_month  = null !== $selected_date ? (int) $selected_date->format( 'n' ) : self::PERIOD_CURRENT_MONTH;
		$selected_year   = null !== $selected_date ? (int) $selected_date->format( 'Y' ) : $current_year;

		if ( '' !== $selection_month ) {
			$selected_month = ctype_digit( $selection_month ) ? (int) $selection_month : $selection_month;
		}

		if ( $selection_year > 0 ) {
			$selected_year = $selection_year;
		}

		$hide_year_field = in_array( $selected_month, [ self::PERIOD_ALL_TIME, self::PERIOD_CURRENT_MONTH, self::PERIOD_LAST_MONTH ], true );
		?>
		<tr>
			<th scope="row">
				<label for="storeaccountant-export-month"><?php esc_html_e( 'Month', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select
					id="storeaccountant-export-month"
					name="storeaccountant_export_month"
					data-storeaccountant-period-provider="month-year"
					data-storeaccountant-period-month="1"
					<?php disabled( $read_only ); ?>
				>
					<?php if ( ! $allow_concrete_months ) : ?>
					<option value="<?php echo esc_attr( self::PERIOD_CURRENT_MONTH ); ?>" <?php selected( $selected_month, self::PERIOD_CURRENT_MONTH ); ?>>
						<?php esc_html_e( 'This month', 'storeaccountant' ); ?>
					</option>
					<option value="<?php echo esc_attr( self::PERIOD_LAST_MONTH ); ?>" <?php selected( $selected_month, self::PERIOD_LAST_MONTH ); ?>>
						<?php esc_html_e( 'Last month', 'storeaccountant' ); ?>
					</option>
					<option value="<?php echo esc_attr( self::PERIOD_ALL_TIME ); ?>" <?php selected( $selected_month, self::PERIOD_ALL_TIME ); ?>>
						<?php esc_html_e( 'All time', 'storeaccountant' ); ?>
					</option>
					<?php else : ?>
					<option value="<?php echo esc_attr( self::PERIOD_ALL_TIME ); ?>" <?php selected( $selected_month, self::PERIOD_ALL_TIME ); ?>>
						<?php esc_html_e( 'All time', 'storeaccountant' ); ?>
					</option>
					<option value="<?php echo esc_attr( self::PERIOD_CURRENT_MONTH ); ?>" <?php selected( $selected_month, self::PERIOD_CURRENT_MONTH ); ?>>
						<?php esc_html_e( 'This month', 'storeaccountant' ); ?>
					</option>
					<option value="<?php echo esc_attr( self::PERIOD_LAST_MONTH ); ?>" <?php selected( $selected_month, self::PERIOD_LAST_MONTH ); ?>>
						<?php esc_html_e( 'Last month', 'storeaccountant' ); ?>
					</option>
					<option value="" disabled="disabled">--------</option>
						<?php foreach ( $this->get_months() as $month_number => $month_label ) : ?>
						<option value="<?php echo esc_attr( (string) $month_number ); ?>" <?php selected( $selected_month, $month_number ); ?>>
							<?php echo esc_html( $month_label ); ?>
						</option>
					<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</td>
		</tr>
		<?php if ( $allow_concrete_months ) : ?>
		<tr
			data-storeaccountant-period-year-row="1"
			<?php if ( $hide_year_field ) : ?>
				class="storeaccountant-is-hidden"
			<?php endif; ?>
		>
			<th scope="row">
				<label for="storeaccountant-export-year"><?php esc_html_e( 'Year', 'storeaccountant' ); ?></label>
			</th>
			<td>
				<select id="storeaccountant-export-year" name="storeaccountant_export_year" <?php disabled( $read_only || $hide_year_field ); ?>>
					<?php foreach ( $this->get_years( $current_year ) as $year ) : ?>
						<option value="<?php echo esc_attr( (string) $year ); ?>" <?php selected( $year, $selected_year ); ?>>
							<?php echo esc_html( (string) $year ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Gets a storable period selection from submitted request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_period_selection_from_request( array $request ): array {
		$month_value = isset( $request['storeaccountant_export_month'] ) ? sanitize_key( wp_unslash( $request['storeaccountant_export_month'] ) ) : '';
		$selection   = [
			'provider' => 'month-year',
			'month'    => $month_value,
		];

		if ( ! in_array( $month_value, [ self::PERIOD_ALL_TIME, self::PERIOD_CURRENT_MONTH, self::PERIOD_LAST_MONTH ], true ) ) {
			$selection['year'] = isset( $request['storeaccountant_export_year'] ) ? absint( wp_unslash( $request['storeaccountant_export_year'] ) ) : 0;
		}

		return $selection;
	}

	/**
	 * Resolves a stored period selection to concrete UTC bounds.
	 *
	 * @param array<string, mixed> $selection Stored period selection.
	 *
	 * @return ExportPeriod|WP_Error
	 */
	public function get_period_from_selection( array $selection ): ExportPeriod|WP_Error {
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
	 * Gets localized month labels indexed by month number.
	 *
	 * @return array<int, string>
	 */
	private function get_months(): array {
		$months = [];

		for ( $month = 1; $month <= 12; $month++ ) {
			$months[ $month ] = date_i18n( 'F', mktime( 0, 0, 0, $month, 1 ) );
		}

		return $months;
	}

	/**
	 * Gets selectable years up to the current year.
	 *
	 * @param int $current_year Current WordPress year.
	 *
	 * @return array<int>
	 */
	private function get_years( int $current_year ): array {
		return range(
			$current_year - self::PAST_YEAR_COUNT,
			$current_year
		);
	}

	/**
	 * Gets the selected date in the shop timezone.
	 *
	 * @param ExportPeriod|null $period Current period.
	 */
	private function get_selected_date( ?ExportPeriod $period ): ?DateTimeImmutable {
		if ( null === $period ) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $period->start_at, new DateTimeZone( 'UTC' ) );

		return false !== $date ? $date->setTimezone( wp_timezone() ) : null;
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
