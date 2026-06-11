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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Security\ReversibleCrypto;
use WP_Error;

/**
 * Tests saved export repository metadata writes.
 */
final class ExportRepositoryTest extends TestCase {
	/** @var array<string, mixed> */
	private array $inserted_post = [];

	/** @var array<int, array<string, mixed>> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->inserted_post = [];
		$this->meta          = [];

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-07 13:14:15' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => trim( $value ) );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
		Functions\when( 'do_action' )->justReturn();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_create_writes_export_post_and_lifecycle_metadata(): void {
		$adapter  = $this->createMock( ExportAdapterInterface::class );
		$renderer = $this->createMock( ExportRendererInterface::class );

		$adapter->method( 'get_id' )->willReturn( 'orders' );
		$renderer->method( 'get_id' )->willReturn( 'csv' );

		Functions\when( 'get_posts' )->alias(
			static function ( array $args ): array {
				return isset( $args['title'] ) ? [] : [];
			}
		);
		Functions\when( 'get_post_meta' )->alias( static fn (): string => '' );
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $post, bool $wp_error ): int {
				$this->inserted_post = $post;

				return 123;
			}
		);

		$result = $this->repository()->create(
			'June orders',
			[ new ExportFilterSelection( 'order_date', [ 'month' => '2026-06' ] ) ],
			'local',
			$adapter,
			$renderer,
			9,
			77,
			1,
			[
				'encrypted' => 'encrypted-password',
				'hash'      => 'hash-password',
			]
		);

		$meta = $this->inserted_post['meta_input'];

		self::assertSame( 123, $result );
		self::assertSame( ExportPostType::POST_TYPE, $this->inserted_post['post_type'] );
		self::assertSame( 'publish', $this->inserted_post['post_status'] );
		self::assertSame( 'June orders', $this->inserted_post['post_title'] );
		self::assertSame( 9, $this->inserted_post['post_author'] );
		self::assertSame( ExportStatus::SCHEDULED, $meta[ ExportPostType::META_STATUS ] );
		self::assertSame( 'orders', $meta[ ExportPostType::META_EXPORT_ADAPTER ] );
		self::assertSame( 'csv', $meta[ ExportPostType::META_EXPORT_WRITER ] );
		self::assertSame( 'local', $meta[ ExportPostType::META_STORAGE_ENGINE ] );
		self::assertSame( (string) ExportPostType::MIN_BATCH_SIZE, $meta[ ExportPostType::META_BATCH_SIZE ] );
		self::assertSame( '9', $meta[ ExportPostType::META_TRIGGERED_BY ] );
		self::assertSame( '77', $meta[ ExportPostType::META_CONFIGURATION_ID ] );
		self::assertSame( 'encrypted-password', $meta[ ExportPostType::META_DOWNLOAD_PASSWORD ] );
		self::assertSame( 'hash-password', $meta[ ExportPostType::META_DOWNLOAD_PASSWORD_HASH ] );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $meta[ ExportPostType::META_DOWNLOAD_TOKEN ] );
		self::assertStringContainsString( 'order_date', $meta[ ExportPostType::META_FILTERS ] );
	}

	public function test_exists_with_title_uses_trimmed_title_and_ignores_empty_values(): void {
		Functions\expect( 'get_posts' )
			->once()
			->with(
				[
					'fields'         => 'ids',
					'post_type'      => ExportPostType::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'no_found_rows'  => true,
					'title'          => 'Existing',
				]
			)
			->andReturn( [ 123 ] );

		self::assertFalse( $this->repository()->exists_with_title( '   ' ) );
		self::assertTrue( $this->repository()->exists_with_title( ' Existing ' ) );
	}

	public function test_status_and_progress_methods_write_expected_meta(): void {
		$this->meta[42] = [
			ExportPostType::META_STARTED_AT        => '',
			ExportPostType::META_PROCESSED_ITEMS   => '3',
			ExportPostType::META_PROCESSED_BATCHES => '1',
			ExportPostType::META_TOTAL_ITEMS       => '8',
			ExportPostType::META_TOTAL_BATCHES     => '2',
		];

		$this->mock_meta_access();

		$repository = $this->repository();

		$repository->update_path( 42, 'exports/file.zip' );
		$repository->mark_queued( 42 );
		$repository->mark_processing( 42, 'Working' );
		$repository->initialize_progress( 42, 8, 2 );
		$repository->mark_batch_processed( 42, 5 );

		self::assertSame( 'exports/file.zip', $this->meta[42][ ExportPostType::META_PATH ] );
		self::assertSame( ExportStatus::PROCESSING, $this->meta[42][ ExportPostType::META_STATUS ] );
		self::assertSame( '2026-06-07 13:14:15', $this->meta[42][ ExportPostType::META_STARTED_AT ] );
		self::assertSame( '5', $this->meta[42][ ExportPostType::META_PROCESSED_ITEMS ] );
		self::assertSame( '2', $this->meta[42][ ExportPostType::META_TOTAL_BATCHES ] );
		self::assertSame( '1', $this->meta[42][ ExportPostType::META_PROCESSED_BATCHES ] );
		self::assertFalse( $repository->all_batches_processed( 42 ) );

		$this->meta[42][ ExportPostType::META_TOTAL_ITEMS ]       = '5';
		$this->meta[42][ ExportPostType::META_TOTAL_BATCHES ]     = '1';
		$this->meta[42][ ExportPostType::META_PROCESSED_ITEMS ]   = '5';
		$this->meta[42][ ExportPostType::META_PROCESSED_BATCHES ] = '1';

		self::assertTrue( $repository->all_batches_processed( 42 ) );

		$repository->mark_completed( 42 );

		self::assertSame( ExportStatus::COMPLETED, $this->meta[42][ ExportPostType::META_STATUS ] );
		self::assertSame( 'Export file generated.', $this->meta[42][ ExportPostType::META_CURRENT_STEP ] );
	}

	public function test_reset_failed_and_log_methods_update_error_and_log_metadata(): void {
		$this->meta[42] = [
			ExportPostType::META_LOG_ENTRIES => [
				[ 'message' => 'old' ],
			],
		];

		$this->mock_meta_access();
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );

		$repository = $this->repository();
		$error      = new WP_Error( 'broken_export', ' Broken export ', [ 'batch' => 2 ] );

		$repository->reset_for_retry( 42 );
		$repository->mark_failed_from_error( 42, $error, [ 'source' => 'test' ] );
		$repository->add_log_entry( 42, 'ERROR!', 'Something happened', [ 'export_id' => 42 ], new RuntimeException( 'Boom' ) );

		self::assertSame( ExportStatus::FAILED, $this->meta[42][ ExportPostType::META_STATUS ] );
		self::assertSame( 'Broken export', $this->meta[42][ ExportPostType::META_ERROR_MESSAGE ] );
		self::assertSame( '1', $this->meta[42][ ExportPostType::META_FAILED_BATCHES ] );
		self::assertSame( '', $this->meta[42][ ExportPostType::META_PATH ] );
		self::assertCount( 2, $this->meta[42][ ExportPostType::META_LOG_ENTRIES ] );
		self::assertSame( 'error', $this->meta[42][ ExportPostType::META_LOG_ENTRIES ][1]['level'] );
		self::assertSame( 'Something happened', $this->meta[42][ ExportPostType::META_LOG_ENTRIES ][1]['message'] );
		self::assertSame( RuntimeException::class, $this->meta[42][ ExportPostType::META_LOG_ENTRIES ][1]['exception']['class'] );
	}

	private function mock_meta_access(): void {
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key ): mixed => $this->meta[ $post_id ][ $key ] ?? ''
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $post_id ][ $key ] = $value;
			}
		);
	}

	private function repository(): ExportRepository {
		return new ExportRepository(
			new ExportFilterSelectionSerializer(),
			new DownloadPasswordManager( new ReversibleCrypto() )
		);
	}
}
