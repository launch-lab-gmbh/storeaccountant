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

namespace StoreAccountant\Tests\Unit\Product\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Product\Admin\ProductFieldMappingTabProvider;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use WP_Post;

/**
 * Tests product field mapping configuration tab metadata.
 */
final class ProductFieldMappingTabProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_tab_provider_and_save_action(): void {
		$provider = $this->provider();

		Monkey\Filters\expectAdded( 'storeaccountant_export_configuration_tab_provider' )->once();
		Monkey\Actions\expectAdded( 'admin_post_storeaccountant_save_product_field_mapping' )->once()->with( [ $provider, 'handle_save' ] );

		$provider->register();

		self::assertSame( ProductFieldMappingTabProvider::PROVIDER_ID, $provider->get_id() );
	}

	public function test_supports_product_configurations_only_and_returns_mapping_tab(): void {
		Functions\expect( 'get_post_meta' )
			->twice()
			->with( 42, ExportConfigurationPostType::META_EXPORT_ADAPTER, true )
			->andReturn( ProductExportAdapter::ADAPTER_ID, 'orders' );

		$configuration = new WP_Post(
			[
				'ID'        => 42,
				'post_type' => ExportConfigurationPostType::POST_TYPE,
			]
		);
		$provider      = $this->provider();

		self::assertTrue( $provider->supports( $configuration ) );
		self::assertFalse( $provider->supports( $configuration ) );
		self::assertSame(
			[ ProductFieldMappingTabProvider::TAB_ID => 'Field Mapping' ],
			$provider->get_tabs( $configuration )
		);
	}

	private function provider(): ProductFieldMappingTabProvider {
		return ( new ReflectionClass( ProductFieldMappingTabProvider::class ) )->newInstanceWithoutConstructor();
	}
}
