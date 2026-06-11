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
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Filter\Period\Contract\PeriodProviderInterface;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the period provider registry.
 */
final class PeriodProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_period_provider_hook_and_accepts_only_period_providers(): void {
		$provider = $this->createMock( PeriodProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'month_year' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_filter_period_provider', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-period-provider' ),
					$provider,
				]
			);

		self::assertSame( [ 'month_year' => $provider ], ( new PeriodProviderRegistry() )->get_all() );
	}
}
