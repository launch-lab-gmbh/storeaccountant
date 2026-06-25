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

namespace StoreAccountant\Tests\Unit\Export\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Admin\ExportListPollingResponseFactory;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests export list polling response data.
 */
final class ExportListPollingResponseFactoryTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	private StorageAdapterInterface $storage_adapter;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$this->storage_adapter = $this->createMock( StorageAdapterInterface::class );
		$this->storage_adapter->method( 'get_id' )->willReturn( 'local' );

		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $key, bool $single = false ): mixed {
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( $value ) );
		Functions\when( 'sanitize_file_name' )->alias( static fn ( string $value ): string => preg_replace( '/[^A-Za-z0-9._-]/', '', $value ) ?? '' );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $url ): string => $url );
		Functions\when( 'home_url' )->alias( static fn ( string $path ): string => 'https://example.test' . $path );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'current_time' )->alias(
			static fn ( string $format = 'mysql', bool $gmt = false ): string => 'Y-m-d' === $format ? '2026-06-24' : '2026-06-24 12:00:00'
		);
		Functions\when( 'wp_date' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value, mixed ...$args ): mixed {
				return match ( $hook ) {
					'storeaccountant_storage_adapter' => [ $this->storage_adapter ],
					'storeaccountant_permission_action' => [
						new PermissionAction( PermissionActionIds::EXPORT_DOWNLOAD, 'Download export', 'Exports', StoreAccountantCapabilities::DOWNLOAD_EXPORT ),
					],
					default => $value,
				};
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array(
				$capability,
				[ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::DOWNLOAD_EXPORT ],
				true
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_create_builds_row_data_with_progress_download_and_pollability(): void {
		$this->meta = [
			ExportPostType::META_STATUS            => ExportStatus::COMPLETED,
			ExportPostType::META_CURRENT_STEP      => 'Finalized',
			ExportPostType::META_TOTAL_ITEMS       => '20',
			ExportPostType::META_PROCESSED_ITEMS   => '18',
			ExportPostType::META_TOTAL_BATCHES     => '4',
			ExportPostType::META_PROCESSED_BATCHES => '3',
			ExportPostType::META_STORAGE_ENGINE    => 'local',
			ExportPostType::META_PATH              => 'exports/report.csv',
			ExportPostType::META_DOWNLOAD_TOKEN    => 'secret token',
		];
		$this->storage_adapter->expects( self::once() )
			->method( 'file_exists' )
			->with( 'exports/report.csv' )
			->willReturn( true );

		$response = $this->factory()->create( 123 );

		self::assertSame( 123, $response['id'] );
		self::assertSame( ExportStatus::COMPLETED, $response['status'] );
		self::assertSame( 'Completed', $response['status_label'] );
		self::assertSame( 'Finalized', $response['current_step'] );
		self::assertSame( 18, $response['processed_items'] );
		self::assertSame( 20, $response['total_items'] );
		self::assertSame( '3 / 4 batches, 18 / 20 items', $response['progress_label'] );
		self::assertSame( 'https://example.test/storeaccountant/export-download/secret%20token/', $response['download_url'] );
		self::assertFalse( $response['pollable'] );
	}

	public function test_pollability_matches_active_and_terminal_statuses(): void {
		$this->meta = [
			ExportPostType::META_STATUS      => ExportStatus::QUEUED,
			ExportPostType::META_EXPORTED_AT => '2026-06-24 09:00:00',
		];
		self::assertTrue( $this->factory()->is_export_pollable( 1 ) );

		$this->meta = [
			ExportPostType::META_STATUS      => ExportStatus::PROCESSING,
			ExportPostType::META_EXPORTED_AT => '2026-06-24 10:00:00',
		];
		self::assertTrue( $this->factory()->is_export_pollable( 1 ) );

		$this->meta = [ ExportPostType::META_STATUS => ExportStatus::FAILED ];
		self::assertFalse( $this->factory()->is_export_pollable( 1 ) );

		$this->meta = [ ExportPostType::META_STATUS => ExportStatus::COMPLETED ];
		self::assertFalse( $this->factory()->is_export_pollable( 1 ) );
	}

	public function test_old_active_exports_are_not_pollable(): void {
		$this->meta = [
			ExportPostType::META_STATUS      => ExportStatus::PROCESSING,
			ExportPostType::META_EXPORTED_AT => '2026-06-23 23:59:59',
		];

		self::assertFalse( $this->factory()->is_export_pollable( 1 ) );
	}

	public function test_scheduled_exports_are_pollable_only_inside_window(): void {
		$now = time();

		$this->meta = [
			ExportPostType::META_STATUS        => ExportStatus::SCHEDULED,
			ExportPostType::META_SCHEDULED_FOR => (string) ( $now + 299 ),
		];
		self::assertTrue( $this->factory()->is_export_pollable( 1 ) );

		$this->meta[ ExportPostType::META_SCHEDULED_FOR ] = (string) ( $now + 301 );
		self::assertFalse( $this->factory()->is_export_pollable( 1 ) );
	}

	public function test_missing_storage_file_removes_download_action(): void {
		$this->meta = [
			ExportPostType::META_STATUS         => ExportStatus::COMPLETED,
			ExportPostType::META_STORAGE_ENGINE => 'local',
			ExportPostType::META_PATH           => 'exports/missing.csv',
			ExportPostType::META_DOWNLOAD_TOKEN => 'token',
		];
		$this->storage_adapter->expects( self::once() )
			->method( 'file_exists' )
			->with( 'exports/missing.csv' )
			->willReturn( false );

		self::assertNull( $this->factory()->create( 123 )['download_url'] );
	}

	private function factory(): ExportListPollingResponseFactory {
		return new ExportListPollingResponseFactory(
			new StorageAdapterRegistry(),
			new ExportDownloadUrlFactory(),
			new PermissionChecker( new PermissionActionRegistry() )
		);
	}
}
