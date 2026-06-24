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

namespace StoreAccountant\Tests\Unit\Export\Queue\Handler;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\Handler\FinalizeExportAttachmentsMessageHandler;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Contract\ChunkedStorageAdapterInterface;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use WP_Error;

/**
 * Tests queued export attachment finalization chunks.
 */
final class FinalizeExportAttachmentsMessageHandlerTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	/** @var array<int, object> */
	private array $dispatched = [];

	private string $tmp_dir = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->meta       = [];
		$this->dispatched = [];
		$this->tmp_dir    = sys_get_temp_dir() . '/storeaccountant-attachment-finalize-' . bin2hex( random_bytes( 4 ) );

		mkdir( $this->tmp_dir, 0777, true );

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
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-24 12:00:00' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => $value );
		Functions\when( 'get_post_type' )->alias( static fn (): string => ExportPostType::POST_TYPE );
		Functions\when( 'wp_upload_dir' )->alias( fn (): array => [ 'basedir' => $this->tmp_dir ] );
		Functions\when( 'get_temp_dir' )->alias( fn (): string => $this->tmp_dir );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true ) );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);
		Functions\when( 'do_action' )->justReturn();
		Functions\when( 'apply_filters' )->alias(
			function ( string $hook, mixed $value ): mixed {
				if ( 'storeaccountant_storage_adapter' === $hook ) {
					return [ $this->storage_adapter() ];
				}

				return $value;
			}
		);
		Functions\when( 'get_post_meta' )->alias( fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? '' );
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
	}

	protected function tearDown(): void {
		$this->remove_directory( $this->tmp_dir );
		unset( $GLOBALS['wp_filesystem'] );

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_non_final_attachment_chunk_queues_next_remainder_offset(): void {
		$this->prepare_export_with_attachments( 206 );

		$result = $this->handler()->__invoke(
			new FinalizeExportAttachmentsMessage( 42, 'csv', 'exports/token.zip', 100, 100, 206 )
		);

		self::assertTrue( $result );
		self::assertSame( 100, $this->meta['appended_attachment_count'] );
		self::assertCount( 1, $this->dispatched );
		self::assertInstanceOf( FinalizeExportAttachmentsMessage::class, $this->dispatched[0] );
		self::assertSame( 200, $this->dispatched[0]->offset );
		self::assertSame( 100, $this->dispatched[0]->limit );
		self::assertArrayNotHasKey( ExportPostType::META_PATH, $this->meta );
		self::assertSame( ExportStatus::PROCESSING, $this->meta[ ExportPostType::META_STATUS ] );
	}

	public function test_final_attachment_chunk_processes_remainder_and_completes_export(): void {
		$this->prepare_export_with_attachments( 206 );

		$result = $this->handler()->__invoke(
			new FinalizeExportAttachmentsMessage( 42, 'csv', 'exports/token.zip', 200, 100, 206 )
		);

		self::assertTrue( $result );
		self::assertSame( 6, $this->meta['appended_attachment_count'] );
		self::assertSame( [], $this->dispatched );
		self::assertSame( 'exports/token.zip', $this->meta[ ExportPostType::META_PATH ] );
		self::assertSame( ExportStatus::COMPLETED, $this->meta[ ExportPostType::META_STATUS ] );
		self::assertDirectoryDoesNotExist( $this->tmp_dir . '/storeaccountant/tmp/exports/42' );
	}

	private function prepare_export_with_attachments( int $attachment_count ): void {
		$this->meta = [
			ExportPostType::META_STATUS         => ExportStatus::PROCESSING,
			ExportPostType::META_STORAGE_ENGINE => 'local',
			ExportPostType::META_LOG_ENTRIES    => [],
			ExportPostType::META_STARTED_AT     => '2026-06-24 11:59:00',
		];

		$store = new BatchExportStore();

		self::assertTrue(
			$store->save_batch(
				42,
				1,
				new ExportDataset(
					new FieldCollection( [ new Field( 'order_id', 'Order ID' ) ] ),
					[],
					$this->attachments( $attachment_count )
				)
			)
		);
	}

	/**
	 * @return iterable<ExportAttachment>
	 */
	private function attachments( int $attachment_count ): iterable {
		for ( $index = 1; $index <= $attachment_count; ++$index ) {
			$stream = fopen( 'php://temp', 'rb+' );
			self::assertIsResource( $stream );
			fwrite( $stream, 'invoice-' . $index );
			rewind( $stream );

			yield new ExportAttachment(
				$stream,
				sprintf( 'invoice-%03d.pdf', $index ),
				'application/pdf',
				sprintf( 'Invoices/pdf/invoice-%03d.pdf', $index )
			);
		}
	}

	private function handler(): FinalizeExportAttachmentsMessageHandler {
		return new FinalizeExportAttachmentsMessageHandler(
			$this->message_bus(),
			new StorageAdapterRegistry(),
			$this->repository(),
			new BatchExportStore()
		);
	}

	private function storage_adapter(): StorageAdapterInterface&ChunkedStorageAdapterInterface {
		$storage = $this->createMockForIntersectionOfInterfaces(
			[
				StorageAdapterInterface::class,
				ChunkedStorageAdapterInterface::class,
			]
		);
		$storage->method( 'get_id' )->willReturn( 'local' );
		$storage->method( 'append_attachments' )->willReturnCallback(
			function ( string $storage_path, iterable $attachments ): true {
				$count = 0;

				foreach ( $attachments as $attachment ) {
					if ( $attachment instanceof ExportAttachment ) {
						fclose( $attachment->stream );
						++$count;
					}
				}

				$this->meta['appended_attachment_count'] = $count;

				return true;
			}
		);

		return $storage;
	}

	private function message_bus(): MessageBusInterface {
		return new class( $this->dispatched ) implements MessageBusInterface {
			/**
			 * @param array<int, object> $dispatched Dispatched messages.
			 */
			public function __construct(
				private array &$dispatched
			) {}

			public function dispatch( object $message, array $stamps = [] ): Envelope {
				$this->dispatched[] = $message;

				return new Envelope( $message, $stamps );
			}
		};
	}

	private function repository(): ExportRepository {
		return new ExportRepository(
			new ExportFilterSelectionSerializer(),
			new DownloadPasswordManager( new ReversibleCrypto() )
		);
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		foreach ( scandir( $directory ) ?: [] as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? $this->remove_directory( $path ) : unlink( $path );
		}

		rmdir( $directory );
	}
}
