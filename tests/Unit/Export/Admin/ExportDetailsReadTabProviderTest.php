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
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Admin\ExportDetailsReadTabProvider;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests export details read tab behavior.
 */
final class ExportDetailsReadTabProviderTest extends TestCase {
	/** @var array<int, array<string, mixed>> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_wordpress_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_read_tab_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_read_tab_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$this->provider()->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_supports_export_posts_and_returns_details_tab(): void {
		$provider = $this->provider();

		self::assertSame( ExportDetailsReadTabProvider::PROVIDER_ID, $provider->get_id() );
		self::assertTrue( $provider->supports( $this->post( ExportPostType::POST_TYPE ) ) );
		self::assertFalse( $provider->supports( $this->post( 'post' ) ) );
		self::assertSame( [ ExportDetailsReadTabProvider::TAB_ID => 'Export Details' ], $provider->get_tabs( $this->post() ) );
	}

	public function test_render_formats_status_storage_download_and_protected_password(): void {
		$this->meta[77] = [
			ExportPostType::META_STATUS            => ExportStatus::COMPLETED,
			ExportPostType::META_CURRENT_STEP      => 'Finalized',
			ExportPostType::META_PROCESSED_BATCHES => '2',
			ExportPostType::META_TOTAL_BATCHES     => '4',
			ExportPostType::META_PROCESSED_ITEMS   => '15',
			ExportPostType::META_TOTAL_ITEMS       => '30',
			ExportPostType::META_STARTED_AT        => '2026-05-01 10:00:00',
			ExportPostType::META_FINISHED_AT       => '2026-05-01 10:00:05',
			ExportPostType::META_EXPORTED_AT       => '2026-05-01 10:00:06',
			ExportPostType::META_EXPORT_ADAPTER    => 'orders',
			ExportPostType::META_EXPORT_WRITER     => 'csv',
			ExportPostType::META_STORAGE_ENGINE    => 'local',
			ExportPostType::META_PATH              => '/wp-content/uploads/storeaccountant/2026/report.zip#orders.csv',
			ExportPostType::META_TRIGGERED_BY      => '5',
			ExportPostType::META_DOWNLOAD_TOKEN    => 'download-token',
		];

		$output = $this->render( $this->post() );

		self::assertStringContainsString( 'Export ID', $output );
		self::assertStringContainsString( 'May Export', $output );
		self::assertStringContainsString( 'Completed', $output );
		self::assertStringContainsString( 'Finalized', $output );
		self::assertStringContainsString( '2 / 4', $output );
		self::assertStringContainsString( '15 / 30', $output );
		self::assertStringContainsString( '50%', $output );
		self::assertStringContainsString( '5 seconds', $output );
		self::assertStringContainsString( 'export_adapter_orders', $output );
		self::assertStringContainsString( 'exporter_csv', $output );
		self::assertStringContainsString( 'storage_adapter_local', $output );
		self::assertStringContainsString( 'wp-content/uploads/storeaccountant/2026/report.zip', $output );
		self::assertStringContainsString( '2 KB', $output );
		self::assertStringContainsString( 'Alice Admin', $output );
		self::assertStringContainsString( 'Quick Export', $output );
		self::assertStringContainsString( 'https://example.test/storeaccountant/export-download/download-token/', $output );
		self::assertStringContainsString( 'Protected', $output );
	}

	public function test_render_shows_missing_configuration_and_missing_storage_file(): void {
		$this->meta[77] = [
			ExportPostType::META_STATUS           => ExportStatus::COMPLETED,
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_PATH             => 'missing.zip',
			ExportPostType::META_CONFIGURATION_ID => '42',
		];

		$output = $this->render( $this->post() );

		self::assertStringContainsString( 'Deleted configuration', $output );
		self::assertStringContainsString( 'The export file no longer exists in storage.', $output );
	}

	private function render( \WP_Post $post ): string {
		ob_start();
		try {
			$this->provider()->render( ExportDetailsReadTabProvider::TAB_ID, $post );

			return (string) ob_get_clean();
		} catch ( \Throwable $exception ) {
			ob_end_clean();
			throw $exception;
		}
	}

	private function provider(): ExportDetailsReadTabProvider {
		return new ExportDetailsReadTabProvider(
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			new StorageAdapterRegistry(),
			new ExportStoragePathGenerator( new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' ) ),
			new ExportDownloadUrlFactory(),
			new DownloadPasswordManager( new ReversibleCrypto() ),
			new PermissionChecker( new PermissionActionRegistry() )
		);
	}

	private function post( string $post_type = ExportPostType::POST_TYPE ): \WP_Post {
		return new \WP_Post(
			[
				'ID'          => 77,
				'post_type'   => $post_type,
				'post_title'  => 'May Export',
				'post_status' => 'publish',
			]
		);
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_n' )->alias( static fn ( string $single, string $plural, int $number ): string => 1 === $number ? $single : $plural );
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'sanitize_file_name' )->alias( static fn ( string $name ): string => preg_replace( '/[^A-Za-z0-9._-]/', '', $name ) ?? '' );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/' ) . '/' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key, bool $single = false ): mixed => $this->meta[ $post_id ][ $key ] ?? ''
		);
		Functions\when( 'get_the_title' )->alias(
			static fn ( \WP_Post|int $post ): string => $post instanceof \WP_Post ? (string) $post->post_title : 'Configuration'
		);
		Functions\when( 'get_post_status_object' )->alias(
			static fn ( string $status ): object => (object) [ 'label' => ucfirst( $status ) ]
		);
		Functions\when( 'wp_date' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( 'Y-m-d H:i:s', $timestamp ) );
		Functions\when( 'get_user_locale' )->alias( static fn (): string => 'en_US' );
		Functions\when( 'get_option' )->alias(
			static fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'date_format' => 'Y-m-d',
				'time_format' => 'H:i:s',
				default => $default,
			}
		);
		Functions\when( 'size_format' )->alias( static fn ( int $bytes ): string => (int) round( $bytes / 1024 ) . ' KB' );
		Functions\when( 'get_user_by' )->alias(
			static fn ( string $field, int $id ): object|false => 5 === $id ? (object) [ 'display_name' => 'Alice Admin' ] : false
		);
		Functions\when( 'get_post' )->alias( static fn (): false => false );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . ltrim( $path, '/' ) );
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $args, string $url ): string {
				return $url . '?' . http_build_query( $args );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_export_adapter' => [ $this->export_adapter( 'orders' ) ],
				'storeaccountant_export_renderer' => [ $this->renderer( 'csv' ) ],
				'storeaccountant_storage_adapter' => [ $this->storage_adapter( 'local' ) ],
				'storeaccountant_permission_action' => [
					new PermissionAction( PermissionActionIds::CONFIGURATION_VIEW, 'View configuration', 'Configurations', StoreAccountantCapabilities::VIEW_CONFIGURATION ),
					new PermissionAction( PermissionActionIds::EXPORT_VIEW_LOG, 'View export log', 'Exports', StoreAccountantCapabilities::VIEW_EXPORT_LOG ),
					new PermissionAction( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS, 'View passwords', 'Settings', StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ),
				],
				default => $value,
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array( $capability, [ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::VIEW_CONFIGURATION, StoreAccountantCapabilities::VIEW_EXPORT_LOG ], true )
		);
	}

	private function export_adapter( string $id ): ExportAdapterInterface {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );

		return $adapter;
	}

	private function renderer( string $id ): ExportRendererInterface {
		$renderer = $this->createMock( ExportRendererInterface::class );
		$renderer->method( 'get_id' )->willReturn( $id );

		return $renderer;
	}

	private function storage_adapter( string $id ): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );
		$adapter->method( 'file_exists' )->willReturnCallback( static fn ( string $path ): bool => '2026/report.zip' === $path );
		$adapter->method( 'get_file_size' )->willReturn( 2048 );

		return $adapter;
	}
}
