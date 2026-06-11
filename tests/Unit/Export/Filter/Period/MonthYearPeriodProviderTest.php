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

namespace StoreAccountant\Tests\Unit\Export\Filter\Period;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;

/**
 * Tests month/year period resolution.
 */
final class MonthYearPeriodProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_period_provider_filter(): void {
		$provider = new MonthYearPeriodProvider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertTrue( true );
	}

	public function test_get_id_returns_stable_provider_id(): void {
		self::assertSame( MonthYearPeriodProvider::PROVIDER_ID, ( new MonthYearPeriodProvider() )->get_id() );
	}

	public function test_resolve_returns_concrete_month_bounds_in_utc(): void {
		Functions\expect( 'sanitize_key' )
			->once()
			->with( '5' )
			->andReturn( '5' );

		Functions\expect( 'wp_timezone' )
			->once()
			->andReturn( new DateTimeZone( 'Europe/Berlin' ) );

		Functions\expect( 'absint' )
			->once()
			->with( 2026 )
			->andReturn( 2026 );

		Functions\expect( 'current_time' )
			->once()
			->with( 'Y' )
			->andReturn( '2026' );

		$period = ( new MonthYearPeriodProvider() )->resolve(
			[
				'month' => '5',
				'year'  => 2026,
			]
		);

		self::assertInstanceOf( ExportPeriod::class, $period );
		self::assertSame( '2026-04-30 22:00:00', $period->start_at );
		self::assertSame( '2026-05-31 21:59:59', $period->end_at );
	}

	public function test_format_label_formats_period_in_wordpress_timezone(): void {
		Functions\expect( 'wp_timezone' )
			->once()
			->andReturn( new DateTimeZone( 'Europe/Berlin' ) );

		Functions\expect( 'wp_date' )
			->once()
			->with( 'F Y', Mockery::type( 'int' ) )
			->andReturn( 'May 2026' );

		self::assertSame(
			'May 2026',
			( new MonthYearPeriodProvider() )->format_label( new ExportPeriod( '2026-04-30 22:00:00', '2026-05-31 21:59:59' ) )
		);
	}
}
