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

namespace StoreAccountant\Tests\Unit\Storage\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;

/**
 * Tests local storage path configuration.
 */
final class LocalStorageConfigurationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_from_wordpress_uploads_builds_default_configuration(): void {
		Functions\expect( 'wp_upload_dir' )
			->once()
			->with( null, false )
			->andReturn(
				[
					'basedir' => '/var/www/html/wp-content/uploads',
					'error'   => false,
				]
			);

		Functions\expect( 'trailingslashit' )
			->once()
			->with( '/var/www/html/wp-content/uploads' )
			->andReturn( '/var/www/html/wp-content/uploads/' );

		$configuration = LocalStorageConfiguration::from_wordpress_uploads();

		self::assertInstanceOf( LocalStorageConfiguration::class, $configuration );
		self::assertSame( '/var/www/html/wp-content/uploads/storeaccountant', $configuration->root_path );
		self::assertSame( 'wp-content/uploads/storeaccountant', $configuration->display_root_path );
	}

	public function test_get_root_path_returns_configured_root_path(): void {
		$configuration = new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' );

		self::assertSame( '/tmp/storeaccountant', $configuration->get_root_path() );
	}

	public function test_get_archive_path_returns_root_directory_for_empty_archive_file(): void {
		$configuration = new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( '/tmp/storeaccountant' )
			->andReturn( false );

		Functions\expect( 'trailingslashit' )
			->once()
			->with( '/tmp/storeaccountant' )
			->andReturn( '/tmp/storeaccountant/' );

		self::assertSame( '/tmp/storeaccountant/', $configuration->get_archive_path( '' ) );
	}

	public function test_get_archive_path_creates_missing_archive_directory(): void {
		$root          = sys_get_temp_dir() . '/storeaccountant-unit-root';
		$configuration = new LocalStorageConfiguration( $root, 'wp-content/uploads/storeaccountant' );

		Functions\expect( 'is_wp_error' )
			->once()
			->with( $root )
			->andReturn( false );

		Functions\expect( 'trailingslashit' )
			->once()
			->with( $root )
			->andReturn( $root . '/' );

		Functions\expect( 'wp_mkdir_p' )
			->once()
			->with( $root . '/exports' )
			->andReturn( true );

		self::assertSame( $root . '/exports/token.zip', $configuration->get_archive_path( 'exports/token.zip' ) );
	}
}
