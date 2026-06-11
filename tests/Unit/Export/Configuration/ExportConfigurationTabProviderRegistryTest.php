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

namespace StoreAccountant\Tests\Unit\Export\Configuration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationTabProviderRegistry;
use StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the export configuration tab provider registry.
 */
final class ExportConfigurationTabProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_configuration_tab_provider_hook_and_accepts_only_tab_providers(): void {
		$provider = $this->createMock( ExportConfigurationTabProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'field_mapping' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_configuration_tab_provider', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-tab-provider' ),
					$provider,
				]
			);

		self::assertSame( [ 'field_mapping' => $provider ], ( new ExportConfigurationTabProviderRegistry() )->get_all() );
	}
}
