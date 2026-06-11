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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\ExportReadTabProviderInterface;
use StoreAccountant\Export\ExportReadTabProviderRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the export read tab provider registry.
 */
final class ExportReadTabProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_read_tab_provider_hook_and_accepts_only_read_tab_providers(): void {
		$provider = $this->createMock( ExportReadTabProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'details' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_read_tab_provider', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-read-tab-provider' ),
					$provider,
				]
			);

		self::assertSame( [ 'details' => $provider ], ( new ExportReadTabProviderRegistry() )->get_all() );
	}
}
