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

namespace StoreAccountant\Tests\Unit\Export\Filter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportPeriod;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Export\Filter\Period\Contract\PeriodProviderInterface;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;

/**
 * Tests export filter snapshotting.
 */
final class ExportFilterSnapshotterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_snapshot_resolves_dynamic_period_selection(): void {
		$selection = new ExportFilterSelection(
			'order_date',
			[
				'period_provider' => 'Month Year',
				'period'          => [
					'month' => '5',
					'year'  => 2026,
				],
			]
		);

		$provider = $this->createMock( PeriodProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'month_year' );
		$provider->expects( self::once() )
			->method( 'resolve' )
			->with(
				[
					'month' => '5',
					'year'  => 2026,
				]
			)
			->willReturn( new ExportPeriod( '2026-05-01 00:00:00', '2026-05-31 23:59:59' ) );

		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'Month Year' )
			->andReturn( 'month_year' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', [] )
			->andReturn( [ $provider ] );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		$snapshots = ( new ExportFilterSnapshotter( new PeriodProviderRegistry() ) )->snapshot( [ $selection ] );

		self::assertIsArray( $snapshots );
		self::assertSame( 'order_date', $snapshots[0]->filter_id );
		self::assertSame(
			[
				'period_provider' => 'Month Year',
				'period'          => [
					'month' => '5',
					'year'  => 2026,
				],
				'resolved_period' => [
					'start_at' => '2026-05-01 00:00:00',
					'end_at'   => '2026-05-31 23:59:59',
				],
			],
			$snapshots[0]->settings
		);
	}

	public function test_snapshot_keeps_selection_without_dynamic_period_unchanged(): void {
		$selection = new ExportFilterSelection( 'order_status', [ 'statuses' => [ 'wc-completed' ] ] );

		Functions\expect( 'apply_filters' )->never();

		$snapshots = ( new ExportFilterSnapshotter( new PeriodProviderRegistry() ) )->snapshot( [ $selection ] );

		self::assertIsArray( $snapshots );
		self::assertNotSame( $selection, $snapshots[0] );
		self::assertSame( $selection->filter_id, $snapshots[0]->filter_id );
		self::assertSame( $selection->settings, $snapshots[0]->settings );
	}
}
