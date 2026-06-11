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

namespace StoreAccountant\Tests\Unit\Settings\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Settings\Admin\PluginSettingsTabProviderRegistry;
use StoreAccountant\Settings\Contract\PluginSettingsTabProviderInterface;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests plugin settings tab provider registry behavior.
 */
final class PluginSettingsTabProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_settings_tab_provider_hook_and_filters_by_type(): void {
		$provider = $this->createMock( PluginSettingsTabProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'security' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_plugin_settings_tab_provider', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $provider ] );

		self::assertSame( [ 'security' => $provider ], ( new PluginSettingsTabProviderRegistry() )->get_all() );
	}
}
