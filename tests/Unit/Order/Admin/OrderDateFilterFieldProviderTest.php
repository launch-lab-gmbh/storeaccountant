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

namespace StoreAccountant\Tests\Unit\Order\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Order\Admin\OrderDateFilterFieldProvider;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Export\Filter\OrderDateFilter;

/**
 * Tests the order date filter field provider.
 */
final class OrderDateFilterFieldProviderTest extends TestCase {
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

		self::assertSame( OrderDateFilter::FILTER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( OrderExportAdapter::ADAPTER_ID ) );
		self::assertFalse( $provider->supports( 'customers' ) );
	}

	public function test_get_selection_from_request_delegates_period_selection_and_sanitizes_date_field(): void {
		$this->mock_period_functions();

		$selection = $this->provider()->get_selection_from_request(
			[
				OrderDateFilterFieldProvider::FIELD_DATE_FIELD => OrderDateFilter::FIELD_DATE_PAID,
				'storeaccountant_export_month' => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			]
		);

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( OrderDateFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( OrderDateFilter::FIELD_DATE_PAID, $selection->settings['date_field'] );
		self::assertSame( MonthYearPeriodProvider::PROVIDER_ID, $selection->settings['period_provider'] );
		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			],
			$selection->settings['period']
		);
	}

	public function test_get_selection_from_request_falls_back_to_created_date_for_invalid_field(): void {
		$this->mock_period_functions();

		$selection = $this->provider()->get_selection_from_request(
			[
				OrderDateFilterFieldProvider::FIELD_DATE_FIELD => 'not-a-date-field',
				'storeaccountant_export_month' => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			]
		);

		self::assertInstanceOf( ExportFilterSelection::class, $selection );
		self::assertSame( OrderDateFilter::FIELD_DATE_CREATED, $selection->settings['date_field'] );
	}

	public function test_get_default_selection_is_stable(): void {
		$selection = $this->provider()->get_default_selection();

		self::assertSame( OrderDateFilter::FILTER_ID, $selection->filter_id );
		self::assertSame( OrderDateFilter::FIELD_DATE_CREATED, $selection->settings['date_field'] );
		self::assertSame( MonthYearPeriodProvider::PROVIDER_ID, $selection->settings['period_provider'] );
		self::assertSame(
			[
				'provider' => MonthYearPeriodProvider::PROVIDER_ID,
				'month'    => MonthYearPeriodProvider::PERIOD_CURRENT_MONTH,
			],
			$selection->settings['period']
		);
	}

	private function provider(): OrderDateFilterFieldProvider {
		return new OrderDateFilterFieldProvider( new MonthYearExportPeriodFieldProvider() );
	}

	private function mock_period_functions(): void {
		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $value ) ?? '' )
		);
		Functions\when( 'wp_timezone' )->alias( static fn (): DateTimeZone => new DateTimeZone( 'UTC' ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
	}
}
