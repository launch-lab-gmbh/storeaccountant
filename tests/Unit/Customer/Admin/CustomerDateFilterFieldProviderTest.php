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

namespace StoreAccountant\Tests\Unit\Customer\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Customer\Admin\CustomerDateFilterFieldProvider;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Filter\CustomerDateFilter;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use WP_Error;

/**
 * Tests the customer date filter field provider.
 */
final class CustomerDateFilterFieldProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_filter_field_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter_field_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->provider()->register();

		self::assertTrue( true );
	}

	public function test_get_id_and_supports_are_stable(): void {
		$provider = $this->provider();

		self::assertSame( CustomerDateFilter::FILTER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( CustomerExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports( 'orders' ) );
	}

	public function test_get_selection_from_request_delegates_to_period_field_provider(): void {
		$this->mock_period_functions();

		$selection = $this->provider()->get_selection_from_request(
			[
				'storeaccountant_export_month' => '5',
				'storeaccountant_export_year'  => '2026',
			]
		);

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( CustomerDateFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( CustomerDateFilter::FIELD_DATE_CREATED, $selection->settings['date_field'] );
		self::assertSame( MonthYearPeriodProvider::PROVIDER_ID, $selection->settings['period_provider'] );
		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => '5',
				'year'     => 2026,
			],
			$selection->settings['period']
		);
	}

	public function test_get_selection_from_request_returns_period_errors(): void {
		$this->mock_period_functions();

		$result = $this->provider()->get_selection_from_request(
			[
				'storeaccountant_export_month' => 'not-a-month',
			]
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_invalid_period', $result->get_error_code() );
	}

	public function test_get_default_selection_is_current_month(): void {
		$selection = $this->provider()->get_default_selection();

		self::assertSame( CustomerDateFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( CustomerDateFilter::FIELD_DATE_CREATED, $selection->settings['date_field'] );
		self::assertSame( MonthYearPeriodProvider::PROVIDER_ID, $selection->settings['period_provider'] );
		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			],
			$selection->settings['period']
		);
	}

	public function test_render_delegates_period_selection_to_month_year_fields(): void {
		Functions\when( 'current_time' )->alias( static fn ( string $format ): string|int => 'Y' === $format ? '2026' : 0 );
		Functions\when( 'date_i18n' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );
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

		ob_start();
		$this->provider()->render(
			new ExportFilterSelection(
				CustomerDateFilter::FILTER_ID,
				[
					'period' => [
						'provider' => MonthYearPeriodProvider::PROVIDER_ID,
						'month'    => '5',
						'year'     => 2026,
					],
				]
			)
		);
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'name="storeaccountant_export_month"', $output );
		self::assertMatchesRegularExpression( '/value="5"\\s+selected="selected"/', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_year"', $output );
		self::assertMatchesRegularExpression( '/value="2026"\\s+selected="selected"/', $output );
	}

	private function provider(): CustomerDateFilterFieldProvider {
		return new CustomerDateFilterFieldProvider( new MonthYearExportPeriodFieldProvider() );
	}

	private function mock_period_functions(): void {
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'wp_timezone' )->alias( static fn (): DateTimeZone => new DateTimeZone( 'UTC' ) );
		Functions\when( 'current_time' )->alias( static fn ( string $format ): string|int => 'Y' === $format ? '2026' : 0 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
	}
}
