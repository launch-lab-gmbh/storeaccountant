<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Tests\Unit\Export\Queue;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\ExportTemporaryFilesCleanupSubscriber;

/**
 * Tests cleanup of temporary files for failed exports.
 */
final class ExportTemporaryFilesCleanupSubscriberTest extends TestCase {
	private string $upload_dir = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->upload_dir = sys_get_temp_dir() . '/storeaccountant-failed-cleanup-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->upload_dir, 0777, true );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			eval( 'namespace { function WP_Filesystem(): bool { return true; } }' );
		}

		$GLOBALS['wp_filesystem'] = new class() {
			public function delete( string $path, bool $recursive = false, string|false $type = false ): bool {
				if ( ! is_dir( $path ) ) {
					return false;
				}

				$items = scandir( $path );

				if ( false === $items ) {
					return false;
				}

				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$item_path = $path . DIRECTORY_SEPARATOR . $item;

					if ( is_dir( $item_path ) ) {
						if ( ! $recursive || ! $this->delete( $item_path, true, 'd' ) ) {
							return false;
						}

						continue;
					}

					if ( is_file( $item_path ) && ! unlink( $item_path ) ) {
						return false;
					}
				}

				return rmdir( $path );
			}
		};

		Functions\when( 'wp_upload_dir' )->alias( fn (): array => [ 'basedir' => $this->upload_dir ] );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
	}

	protected function tearDown(): void {
		$this->delete_directory( $this->upload_dir );
		unset( $GLOBALS['wp_filesystem'] );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_subscribed_events_registers_failed_export_cleanup(): void {
		self::assertSame(
			[
				ExportEvents::FAILED->value => [
					[ 'cleanup_failed_export', 20, 1 ],
				],
			],
			ExportTemporaryFilesCleanupSubscriber::get_subscribed_events()
		);
	}

	public function test_cleanup_failed_export_deletes_concrete_export_temp_directory(): void {
		$directory = $this->upload_dir . '/storeaccountant/tmp/exports/42';

		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/batch-00001.dat', '{}' );
		file_put_contents( $directory . '/nested/generated-list.dat', 'temporary export list' );

		( new ExportTemporaryFilesCleanupSubscriber( new BatchExportStore() ) )->cleanup_failed_export( 42 );

		self::assertDirectoryDoesNotExist( $directory );
		self::assertDirectoryExists( $this->upload_dir . '/storeaccountant/tmp/exports' );
	}

	private function delete_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$items = scandir( $directory );

		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );
				continue;
			}

			unlink( $path );
		}

		rmdir( $directory );
	}
}
