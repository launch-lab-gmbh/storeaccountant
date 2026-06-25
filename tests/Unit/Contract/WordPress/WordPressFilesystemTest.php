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

namespace StoreAccountant\Tests\Unit\Contract\WordPress;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\WordPress\WordPressFilesystem;

/**
 * Tests the WordPress filesystem wrapper.
 */
final class WordPressFilesystemTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$GLOBALS['wp_filesystem'] = null;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['wp_filesystem'] );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_put_contents_delegates_to_initialized_filesystem(): void {
		$filesystem               = new class() {
			public array $calls = [];

			public function put_contents( string $path, string $contents ): bool {
				$this->calls[] = [ $path, $contents ];

				return true;
			}
		};
		$GLOBALS['wp_filesystem'] = $filesystem;

		Functions\expect( 'WP_Filesystem' )->never();

		self::assertTrue( WordPressFilesystem::put_contents( '/tmp/export.csv', 'contents' ) );
		self::assertSame( [ [ '/tmp/export.csv', 'contents' ] ], $filesystem->calls );
	}

	public function test_get_contents_delegates_to_initialized_filesystem_and_returns_contents(): void {
		$filesystem               = new class() {
			public function get_contents( string $path ): string {
				return 'read from ' . $path;
			}
		};
		$GLOBALS['wp_filesystem'] = $filesystem;

		Functions\expect( 'WP_Filesystem' )->never();

		self::assertSame( 'read from /tmp/export.csv', WordPressFilesystem::get_contents( '/tmp/export.csv' ) );
	}

	public function test_rmdir_delegates_to_initialized_filesystem(): void {
		$filesystem               = new class() {
			public array $removed = [];

			public function rmdir( string $path ): bool {
				$this->removed[] = $path;

				return true;
			}
		};
		$GLOBALS['wp_filesystem'] = $filesystem;

		Functions\expect( 'WP_Filesystem' )->never();

		self::assertTrue( WordPressFilesystem::rmdir( '/tmp/storeaccountant' ) );
		self::assertSame( [ '/tmp/storeaccountant' ], $filesystem->removed );
	}

	public function test_delete_delegates_to_initialized_filesystem(): void {
		$filesystem               = new class() {
			public array $deleted = [];

			public function delete( string $path, bool $recursive, string|false $type ): bool {
				$this->deleted[] = [ $path, $recursive, $type ];

				return true;
			}
		};
		$GLOBALS['wp_filesystem'] = $filesystem;

		Functions\expect( 'WP_Filesystem' )->never();

		self::assertTrue( WordPressFilesystem::delete( '/tmp/storeaccountant/tmp/exports/42', true, 'd' ) );
		self::assertSame( [ [ '/tmp/storeaccountant/tmp/exports/42', true, 'd' ] ], $filesystem->deleted );
	}

	public function test_methods_return_failure_values_when_filesystem_initialization_fails(): void {
		$GLOBALS['wp_filesystem'] = null;

		Functions\expect( 'WP_Filesystem' )
			->times( 4 )
			->andReturn( false );

		self::assertFalse( WordPressFilesystem::put_contents( '/tmp/export.csv', 'contents' ) );
		self::assertFalse( WordPressFilesystem::get_contents( '/tmp/export.csv' ) );
		self::assertFalse( WordPressFilesystem::rmdir( '/tmp/storeaccountant' ) );
		self::assertFalse( WordPressFilesystem::delete( '/tmp/storeaccountant/tmp/exports/42', true, 'd' ) );
	}

	public function test_methods_use_filesystem_created_during_initialization(): void {
		$filesystem = new class() {
			public function get_contents( string $path ): string {
				return 'created filesystem: ' . $path;
			}
		};

		Functions\expect( 'WP_Filesystem' )
			->once()
			->andReturnUsing(
				static function () use ( $filesystem ): bool {
					$GLOBALS['wp_filesystem'] = $filesystem;

					return true;
				}
			);

		self::assertSame( 'created filesystem: /tmp/export.csv', WordPressFilesystem::get_contents( '/tmp/export.csv' ) );
	}
}
