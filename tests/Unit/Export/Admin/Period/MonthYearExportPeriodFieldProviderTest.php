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

namespace StoreAccountant\Tests\Unit\Export\Admin\Period;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use WP_Error;

/**
 * Tests the month/year export period field provider.
 */
final class MonthYearExportPeriodFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_period_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_period_selection_from_request_sanitizes_dynamic_and_concrete_values(): void {
		$provider = new MonthYearExportPeriodFieldProvider();

		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			],
			$provider->get_period_selection_from_request( [ 'storeaccountant_export_month' => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH ] )
		);

		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => '5',
				'year'     => 2026,
			],
			$provider->get_period_selection_from_request(
				[
					'storeaccountant_export_month' => '5',
					'storeaccountant_export_year'  => '-2026',
				]
			)
		);
	}

	public function test_get_period_from_selection_resolves_all_time_dynamic_and_concrete_months(): void {
		$provider = new MonthYearExportPeriodFieldProvider();

		$all_time = $provider->get_period_from_selection( [ 'month' => MonthYearPeriodProvider::PERIOD_ALL_TIME ] );
		self::assertInstanceOf( ExportPeriod::class, $all_time );
		self::assertSame( '2016-01-01 00:00:00', $all_time->start_at );

		$current = $provider->get_period_from_selection( [ 'month' => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH ] );
		self::assertInstanceOf( ExportPeriod::class, $current );
		self::assertSame( '2026-06-01 00:00:00', $current->start_at );
		self::assertSame( '2026-06-30 23:59:59', $current->end_at );

		$concrete = $provider->get_period_from_selection(
			[
				'month' => '5',
				'year'  => 2026,
			]
		);
		self::assertInstanceOf( ExportPeriod::class, $concrete );
		self::assertSame( '2026-05-01 00:00:00', $concrete->start_at );
		self::assertSame( '2026-05-31 23:59:59', $concrete->end_at );
	}

	public function test_future_months_and_invalid_values_are_rejected(): void {
		$provider = new MonthYearExportPeriodFieldProvider();

		self::assertInstanceOf(
			WP_Error::class,
			$provider->get_period_from_selection(
				[
					'month' => '7',
					'year'  => 2026,
				]
			)
		);
		self::assertInstanceOf( WP_Error::class, $provider->get_period_from_selection( [ 'month' => 'not-a-month' ] ) );
		self::assertInstanceOf(
			WP_Error::class,
			$provider->get_period_from_selection(
				[
					'month' => '5',
					'year'  => 2015,
				]
			)
		);
	}

	public function test_render_outputs_selected_month_and_year_controls(): void {
		ob_start();
		( new MonthYearExportPeriodFieldProvider() )->render(
			null,
			[
				'month' => '5',
				'year'  => 2026,
			]
		);
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="storeaccountant_export_month"', $output );
		self::assertMatchesRegularExpression( '/value="5"\\s+selected="selected"/', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_year"', $output );
		self::assertMatchesRegularExpression( '/value="2026"\\s+selected="selected"/', $output );
	}

	private function mock_period_functions(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'wp_timezone' )->alias( static fn (): DateTimeZone => new DateTimeZone( 'UTC' ) );
		Functions\when( 'current_time' )->alias(
			static fn ( string $format ): string|int => match ( $format ) {
				'Y' => '2026',
				'n' => '6',
				default => 0,
			}
		);
		Functions\when( 'date_i18n' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );
		Functions\when( 'wp_date' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'selected' )->alias(
			static function ( mixed $selected, mixed $current = true ): void {
				if ( $selected === $current ) {
					echo ' selected="selected"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( bool $disabled ): void {
				if ( $disabled ) {
					echo ' disabled="disabled"';
				}
			}
		);
	}
}
