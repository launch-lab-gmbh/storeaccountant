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
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Contract\ExportRendererSupportsAttachmentsInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\QueuedExportFinalizationResult;
use StoreAccountant\Export\Queue\QueuedExportFinalizer;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\Contract\ChunkedStorageAdapterInterface;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Storage\StorageFileConfiguration;
use WP_Error;

/**
 * Tests queued export finalization from temporary batch fragments.
 */
final class QueuedExportFinalizerTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	private string $upload_dir = '';

	private string $artifact_path = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->meta          = [];
		$this->upload_dir    = sys_get_temp_dir() . '/storeaccountant-finalizer-' . uniqid( '', true );
		$this->artifact_path = '';

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			eval( 'namespace { function WP_Filesystem(): bool { return true; } }' );
		}

		$GLOBALS['wp_filesystem'] = new class() {
			public function put_contents( string $path, string $contents ): bool {
				$directory = dirname( $path );

				if ( ! is_dir( $directory ) ) {
					mkdir( $directory, 0777, true );
				}

				return false !== file_put_contents( $path, $contents );
			}

			public function get_contents( string $path ): string|false {
				return file_get_contents( $path );
			}

			public function rmdir( string $path ): bool {
				return is_dir( $path ) && rmdir( $path );
			}
		};

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_upload_dir' )->alias( fn (): array => [ 'basedir' => $this->upload_dir ] );
		Functions\when( 'wp_mkdir_p' )->alias(
			static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true )
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
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
		if ( '' !== $this->artifact_path && is_file( $this->artifact_path ) ) {
			unlink( $this->artifact_path );
		}

		$this->delete_directory( $this->upload_dir );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_finalize_loads_batches_renders_persists_updates_path_and_deletes_batch_state(): void {
		$this->artifact_path = tempnam( sys_get_temp_dir(), 'storeaccountant-final-artifact-' );
		self::assertIsString( $this->artifact_path );

		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'zipcsv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '77',
			ExportPostType::META_DOWNLOAD_TOKEN   => 'queuedtoken',
		];

		$batch_store = new BatchExportStore();
		$save_result = $batch_store->save_batch(
			42,
			1,
			new ExportDataset(
				new FieldCollection( [ new Field( 'order_id', 'Order ID' ) ] ),
				[ new ExportRecord( '1001', [ new FieldValue( 'order_id', '1001' ) ] ) ],
				[],
				[ 'type' => 'orders' ]
			)
		);

		self::assertTrue( $save_result );

		$adapter  = $this->adapter();
		$renderer = new class( $this->artifact_path ) implements ExportRendererInterface, ExportRendererSupportsAttachmentsInterface {
			public function __construct(
				private readonly string $artifact_path
			) {}

			public function get_id(): string {
				return 'zipcsv';
			}

			public function get_file_extension(): string {
				return 'csv';
			}

			public function get_mime_type(): string {
				return 'text/csv';
			}

			public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
				file_put_contents( $this->artifact_path, 'csv' );

				$GLOBALS['storeaccountant_finalizer_payload_options'] = $payload->options;
				$GLOBALS['storeaccountant_finalizer_record_count']    = count( iterator_to_array( $dataset->records ) );

				return new ExportArtifact( $this->artifact_path, 'csv', 'text/csv', $dataset->attachments );
			}
		};
		$storage  = $this->storage_adapter(
			function ( StorageFileConfiguration $configuration ): string {
				self::assertSame( $this->artifact_path, $configuration->source_path );
				self::assertSame( 'queuedtoken.csv', $configuration->file_name );

				return 'exports/queuedtoken.zip';
			}
		);

		$this->mock_wordpress_state( $adapter, $renderer, $storage );

		$result = $this->finalizer( $batch_store )->finalize( 42 );

		self::assertInstanceOf( QueuedExportFinalizationResult::class, $result );
		self::assertTrue( $result->complete );
		self::assertSame( 'exports/queuedtoken.zip', $this->meta[ ExportPostType::META_PATH ] );
		self::assertSame( 1, $GLOBALS['storeaccountant_finalizer_record_count'] );
		self::assertTrue( $GLOBALS['storeaccountant_finalizer_payload_options'][ ExportPayload::OPTION_INCLUDE_ATTACHMENTS ] );
		self::assertFalse( is_file( $this->artifact_path ) );
		self::assertFalse( is_dir( $this->upload_dir . '/storeaccountant/tmp/exports/42' ) );
	}

	public function test_finalize_starts_chunked_storage_and_returns_pending_attachment_result(): void {
		$this->artifact_path = tempnam( sys_get_temp_dir(), 'storeaccountant-final-artifact-' );
		self::assertIsString( $this->artifact_path );

		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'zipcsv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '77',
			ExportPostType::META_DOWNLOAD_TOKEN   => 'queuedtoken',
			ExportPostType::META_BATCH_SIZE       => '25',
		];

		$batch_store = new BatchExportStore();
		self::assertTrue( $batch_store->save_batch( 42, 1, $this->dataset_with_attachment() ) );

		$renderer = new class( $this->artifact_path ) implements ExportRendererInterface, ExportRendererSupportsAttachmentsInterface {
			public function __construct(
				private readonly string $artifact_path
			) {}

			public function get_id(): string {
				return 'zipcsv';
			}

			public function get_file_extension(): string {
				return 'csv';
			}

			public function get_mime_type(): string {
				return 'text/csv';
			}

			public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
				file_put_contents( $this->artifact_path, 'csv' );

				return new ExportArtifact( $this->artifact_path, 'csv', 'text/csv', $dataset->attachments );
			}
		};
		$storage = $this->createMockForIntersectionOfInterfaces(
			[
				StorageAdapterInterface::class,
				ChunkedStorageAdapterInterface::class,
			]
		);
		$storage->method( 'get_id' )->willReturn( 'local' );
		$storage->expects( self::never() )->method( 'persist' );
		$storage->method( 'start_persist' )->willReturn( 'exports/queuedtoken.zip' );
		$storage->expects( self::never() )->method( 'append_attachments' );

		$this->mock_wordpress_state( $this->adapter(), $renderer, $storage );

		$result = $this->finalizer( $batch_store )->finalize( 42 );

		self::assertInstanceOf( QueuedExportFinalizationResult::class, $result );
		self::assertFalse( $result->complete );
		self::assertSame( 'exports/queuedtoken.zip', $result->storage_path );
		self::assertSame( 1, $result->total_attachments );
		self::assertSame( 25, $result->attachment_batch_size );
		self::assertArrayNotHasKey( ExportPostType::META_PATH, $this->meta );
		self::assertDirectoryExists( $this->upload_dir . '/storeaccountant/tmp/exports/42' );
	}

	public function test_finalize_returns_batch_load_errors_without_persisting(): void {
		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER => 'orders',
			ExportPostType::META_EXPORT_WRITER  => 'csv',
			ExportPostType::META_STORAGE_ENGINE => 'local',
		];

		$adapter  = $this->adapter();
		$renderer = $this->renderer( static fn (): ExportArtifact => new ExportArtifact( '/missing.csv', 'csv', 'text/csv' ) );
		$storage  = $this->storage_adapter( static fn (): string => 'should-not-persist' );

		$this->mock_wordpress_state( $adapter, $renderer, $storage );

		$result = $this->finalizer( new BatchExportStore() )->finalize( 42 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_export_batches_missing', $result->get_error_code() );
		self::assertArrayNotHasKey( ExportPostType::META_PATH, $this->meta );
	}

	private function mock_wordpress_state(
		ExportAdapterInterface $adapter,
		ExportRendererInterface $renderer,
		StorageAdapterInterface $storage
	): void {
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? ''
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, array $items ) use ( $adapter, $renderer, $storage ): array {
				return match ( $hook ) {
					'storeaccountant_export_adapter' => [ $adapter ],
					'storeaccountant_export_renderer' => [ $renderer ],
					'storeaccountant_storage_adapter' => [ $storage ],
					default => $items,
				};
			}
		);
	}

	private function adapter(): ExportAdapterInterface {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );

		return $adapter;
	}

	/**
	 * @param callable(ExportDataset, ExportPayload): ExportArtifact|WP_Error $render Render callback.
	 */
	private function renderer( callable $render ): ExportRendererInterface {
		return new class( $render ) implements ExportRendererInterface {
			public function __construct(
				private readonly mixed $render
			) {}

			public function get_id(): string {
				return 'csv';
			}

			public function get_file_extension(): string {
				return 'csv';
			}

			public function get_mime_type(): string {
				return 'text/csv';
			}

			public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
				return ( $this->render )( $dataset, $payload );
			}
		};
	}

	/**
	 * @param callable(StorageFileConfiguration): string|WP_Error $persist Persist callback.
	 */
	private function storage_adapter( callable $persist ): StorageAdapterInterface {
		$storage = $this->createMock( StorageAdapterInterface::class );
		$storage->method( 'get_id' )->willReturn( 'local' );
		$storage->method( 'persist' )->willReturnCallback( $persist );

		return $storage;
	}

	private function dataset_with_attachment(): ExportDataset {
		$stream = fopen( 'php://temp', 'rb+' );
		self::assertIsResource( $stream );
		fwrite( $stream, 'invoice' );
		rewind( $stream );

		return new ExportDataset(
			new FieldCollection( [ new Field( 'order_id', 'Order ID' ) ] ),
			[ new ExportRecord( '1001', [ new FieldValue( 'order_id', '1001' ) ] ) ],
			[ new \StoreAccountant\Export\Attachment\ExportAttachment( $stream, 'invoice.pdf', 'application/pdf', 'invoices/invoice.pdf' ) ],
			[ 'type' => 'orders' ]
		);
	}

	private function finalizer( BatchExportStore $batch_store ): QueuedExportFinalizer {
		return new QueuedExportFinalizer(
			$batch_store,
			new StorageAdapterRegistry(),
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			new ExportRepository( new ExportFilterSelectionSerializer(), new DownloadPasswordManager( new ReversibleCrypto() ) ),
			new ExportStoragePathGenerator( new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' ) ),
			new ExportFilterSelectionSerializer()
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
