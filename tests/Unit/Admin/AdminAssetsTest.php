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

namespace StoreAccountant\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Admin\AdminAssets;
use StoreAccountant\Admin\AccountingSupportPage;
use StoreAccountant\Export\Admin\AccountingExportPage;
use StoreAccountant\Export\ExportPostType;

/**
 * Tests StoreAccountant admin asset loading decisions.
 */
final class AdminAssetsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		if ( ! defined( 'STOREACCOUNTANT_FILE' ) ) {
			define( 'STOREACCOUNTANT_FILE', dirname( __DIR__, 3 ) . '/storeaccountant.php' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_admin_enqueue_hook(): void {
		$assets = new AdminAssets();

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', [ $assets, 'enqueue' ] );

		$assets->register();

		self::assertTrue( true );
	}

	public function test_enqueue_ignores_unrelated_admin_screen(): void {
		Functions\expect( 'filter_input' )
			->times( 5 )
			->with( INPUT_GET, 'page', FILTER_CALLBACK, [ 'options' => [ \StoreAccountant\Contract\WordPress\Request::class, 'sanitize_key_value' ] ] )
			->andReturn( null );

		Functions\expect( 'wp_enqueue_style' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();

		( new AdminAssets() )->enqueue( 'dashboard_page_example' );

		self::assertTrue( true );
	}

	public function test_enqueue_loads_export_list_assets_and_polling_configuration(): void {
		$this->mock_asset_paths();

		Functions\expect( 'filter_input' )
			->times( 3 )
			->with( INPUT_GET, 'post_type', FILTER_CALLBACK, [ 'options' => [ \StoreAccountant\Contract\WordPress\Request::class, 'sanitize_key_value' ] ] )
			->andReturn( ExportPostType::POST_TYPE );
		Functions\expect( 'filter_input' )
			->times( 9 )
			->with( INPUT_GET, 'page', FILTER_CALLBACK, [ 'options' => [ \StoreAccountant\Contract\WordPress\Request::class, 'sanitize_key_value' ] ] )
			->andReturn( null );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'storeaccountant-admin', 'https://example.test/plugin/assets/css/admin.css', [], Mockery::pattern( '/^0\.1\.0-\d+$/' ) );
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( 'storeaccountant-export-form', 'https://example.test/plugin/assets/js/export-form.js', [], Mockery::pattern( '/^0\.1\.0-\d+$/' ), true );
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with( 'storeaccountant-export-list-polling', 'https://example.test/plugin/assets/js/export-list-polling.js', [], Mockery::pattern( '/^0\.1\.0-\d+$/' ), true );
		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin-ajax.php' )
			->andReturn( 'https://example.test/wp-admin/admin-ajax.php' );
		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( 'storeaccountant_poll_exports' )
			->andReturn( 'nonce-value' );
		Functions\expect( 'wp_json_encode' )
			->once()
			->with(
				[
					'ajaxUrl'    => 'https://example.test/wp-admin/admin-ajax.php',
					'nonce'      => 'nonce-value',
					'intervalMs' => 3000,
					'backoffMs'  => 8000,
				]
			)
			->andReturn( '{"ajaxUrl":"https://example.test/wp-admin/admin-ajax.php"}' );
		Functions\expect( 'wp_add_inline_script' )
			->once()
			->with(
				'storeaccountant-export-list-polling',
				'window.storeAccountantExportPolling = {"ajaxUrl":"https://example.test/wp-admin/admin-ajax.php"};',
				'before'
			);

		( new AdminAssets() )->enqueue( 'edit.php' );

		self::assertTrue( true );
	}

	public function test_enqueue_loads_react_widget_assets_for_export_create_page(): void {
		$this->mock_asset_paths();

		Functions\expect( 'filter_input' )
			->times( 4 )
			->with( INPUT_GET, 'page', FILTER_CALLBACK, [ 'options' => [ \StoreAccountant\Contract\WordPress\Request::class, 'sanitize_key_value' ] ] )
			->andReturn( AccountingExportPage::PAGE_SLUG );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'storeaccountant-admin', 'https://example.test/plugin/assets/css/admin.css', [], Mockery::pattern( '/^0\.1\.0-\d+$/' ) );
		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'wp-components' );
		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'storeaccountant-export-form',
				'https://example.test/plugin/assets/js/export-form.js',
				[ 'wp-components', 'wp-element' ],
				Mockery::pattern( '/^0\.1\.0-\d+$/' ),
				true
			);
		Functions\expect( 'wp_add_inline_script' )->never();

		( new AdminAssets() )->enqueue( 'toplevel_page_storeaccountant-accounting' );

		self::assertTrue( true );
	}

	public function test_enqueue_loads_admin_styles_for_support_page(): void {
		$this->mock_asset_paths();

		Functions\expect( 'filter_input' )
			->times( 11 )
			->with( INPUT_GET, 'page', FILTER_CALLBACK, [ 'options' => [ \StoreAccountant\Contract\WordPress\Request::class, 'sanitize_key_value' ] ] )
			->andReturn( AccountingSupportPage::PAGE_SLUG );

		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'storeaccountant-admin', 'https://example.test/plugin/assets/css/admin.css', [], Mockery::pattern( '/^0\.1\.0-\d+$/' ) );
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_script' )->never();

		( new AdminAssets() )->enqueue( 'settings_page_storeaccountant-support' );

		self::assertTrue( true );
	}

	private function mock_asset_paths(): void {
		Functions\when( 'plugins_url' )->alias(
			static fn ( string $path, string $plugin_file ): string => 'https://example.test/plugin/' . $path
		);
		Functions\when( 'plugin_dir_path' )->alias(
			static fn ( string $plugin_file ): string => dirname( __DIR__, 3 ) . '/'
		);
	}
}
