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

namespace StoreAccountant\Tests\Unit\Export\Queue;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\ExportQueueCleanup;
use StoreAccountant\Security\ReversibleCrypto;
use WP_Post;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3_600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86_400 );
}

/**
 * Tests stale export queue cleanup.
 */
final class ExportQueueCleanupTest extends TestCase {
	/** @var array<string, mixed> */
	private array $updated_meta = [];

	private string $upload_dir;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->updated_meta = [];
		$this->upload_dir   = sys_get_temp_dir() . '/storeaccountant-queue-cleanup-' . uniqid( '', true );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			eval( 'namespace { function WP_Filesystem(): bool { return true; } }' );
		}

		$GLOBALS['wp_filesystem'] = new class() {
			public function rmdir( string $path ): bool {
				return is_dir( $path ) && rmdir( $path );
			}

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

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => $value );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-07 12:00:00' );
		Functions\when( 'wp_upload_dir' )->alias( fn (): array => [ 'basedir' => $this->upload_dir ] );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);
		Functions\when( 'do_action' )->justReturn();
	}

	protected function tearDown(): void {
		$this->delete_directory( $this->upload_dir );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_hooks_cleanup_lifecycle(): void {
		$cleanup = $this->cleanup();

		Monkey\Actions\expectAdded( 'init' )->once()->with( [ $cleanup, 'ensure_scheduled' ] );
		Monkey\Actions\expectAdded( ExportQueueCleanup::HOOK )->once()->with( [ $cleanup, 'cleanup' ] );

		$cleanup->register();

		self::assertTrue( true );
	}

	public function test_ensure_scheduled_adds_daily_event_only_when_missing(): void {
		$scheduled_at = null;
		$before       = time();

		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( ExportQueueCleanup::HOOK )
			->andReturn( false );
		Functions\when( 'wp_schedule_event' )->alias(
			function ( int $timestamp, string $recurrence, string $hook ) use ( &$scheduled_at ): void {
				$scheduled_at = $timestamp;

				self::assertSame( 'daily', $recurrence );
				self::assertSame( ExportQueueCleanup::HOOK, $hook );
			}
		);

		$this->cleanup()->ensure_scheduled();

		$after = time();

		self::assertIsInt( $scheduled_at );
		self::assertGreaterThanOrEqual( $before + ( 2 * HOUR_IN_SECONDS ), $scheduled_at );
		self::assertLessThanOrEqual( $after + ( 2 * HOUR_IN_SECONDS ), $scheduled_at );
	}

	public function test_ensure_scheduled_keeps_existing_event(): void {
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( ExportQueueCleanup::HOOK )
			->andReturn( 123 );
		Functions\expect( 'wp_schedule_event' )->never();

		$this->cleanup()->ensure_scheduled();

		self::assertTrue( true );
	}

	public function test_cleanup_marks_stale_processing_exports_failed_and_deletes_batches(): void {
		$export_id  = 42;
		$batch_path = $this->upload_dir . '/storeaccountant/tmp/exports/' . $export_id . '/batch-00001.dat';

		mkdir( dirname( $batch_path ), 0777, true );
		file_put_contents( $batch_path, '{}' );

		Functions\when( 'get_posts' )->alias(
			static fn (): array => [
				new WP_Post( [ 'ID' => $export_id ] ),
				new WP_Post( [ 'ID' => 84 ] ),
				new WP_Post( [ 'ID' => 126 ] ),
			]
		);
		Functions\when( 'get_post_meta' )->alias(
			static function ( int $post_id, string $key ): string {
				if ( ExportPostType::META_STATUS === $key ) {
					return match ( $post_id ) {
						42, 84 => ExportStatus::PROCESSING,
						default => ExportStatus::COMPLETED,
					};
				}

				if ( ExportPostType::META_STARTED_AT === $key ) {
					return 42 === $post_id ? '2000-01-01 08:00:00' : gmdate( 'Y-m-d H:i:s' );
				}

				return '';
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->updated_meta[ $post_id ][ $key ] = $value;
			}
		);

		$this->cleanup()->cleanup();

		self::assertSame( ExportStatus::FAILED, $this->updated_meta[ $export_id ][ ExportPostType::META_STATUS ] );
		self::assertSame( '1', $this->updated_meta[ $export_id ][ ExportPostType::META_FAILED_BATCHES ] );
		self::assertSame( 'Export generation failed.', $this->updated_meta[ $export_id ][ ExportPostType::META_CURRENT_STEP ] );
		self::assertSame( 'The export queue job timed out.', $this->updated_meta[ $export_id ][ ExportPostType::META_ERROR_MESSAGE ] );
		self::assertFalse( is_dir( dirname( $batch_path ) ) );
		self::assertArrayNotHasKey( 84, $this->updated_meta );
		self::assertArrayNotHasKey( 126, $this->updated_meta );
	}

	private function cleanup(): ExportQueueCleanup {
		return new ExportQueueCleanup(
			new ExportRepository(
				new ExportFilterSelectionSerializer(),
				new DownloadPasswordManager( new ReversibleCrypto() )
			),
			new BatchExportStore()
		);
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
