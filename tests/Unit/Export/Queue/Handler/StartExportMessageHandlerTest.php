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
use StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry;
use StoreAccountant\Export\Contract\BatchExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\Handler\StartExportMessageHandler;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Security\ReversibleCrypto;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use WP_Error;

/**
 * Tests starting queued export batches.
 */
final class StartExportMessageHandlerTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	/** @var array<int, object> */
	private array $dispatched = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->meta       = [];
		$this->dispatched = [];

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-07 15:00:00' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( $value ) );
		Functions\when( 'add_option' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'do_action' )->justReturn();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_invoke_marks_processing_initializes_progress_and_dispatches_batch_messages(): void {
		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '77',
			ExportPostType::META_BATCH_SIZE       => '25',
		];

		$adapter = $this->batch_adapter( 55 );
		$this->mock_wordpress_state( $adapter, $this->renderer() );

		$result = $this->handler()->__invoke( new StartExportMessage( 42, 'csv' ) );

		self::assertTrue( $result );
		self::assertSame( ExportStatus::PROCESSING, $this->meta[ ExportPostType::META_STATUS ] );
		self::assertSame( '55', $this->meta[ ExportPostType::META_TOTAL_ITEMS ] );
		self::assertSame( '3', $this->meta[ ExportPostType::META_TOTAL_BATCHES ] );
		self::assertCount( 3, $this->dispatched );
		self::assertInstanceOf( ProcessExportBatchMessage::class, $this->dispatched[0] );
		self::assertSame( 1, $this->dispatched[0]->batch_number );
		self::assertSame( 0, $this->dispatched[0]->offset );
		self::assertSame( 25, $this->dispatched[0]->limit );
		self::assertSame( 3, $this->dispatched[2]->batch_number );
		self::assertSame( 50, $this->dispatched[2]->offset );
	}

	public function test_empty_export_stores_empty_batch_and_dispatches_finalizer(): void {
		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '77',
			ExportPostType::META_BATCH_SIZE       => '100',
		];

		$this->mock_filesystem();
		$this->mock_wordpress_state( $this->batch_adapter( 0 ), $this->renderer() );

		$result = $this->handler()->__invoke( new StartExportMessage( 42, 'csv' ) );

		self::assertTrue( $result );
		self::assertSame( '0', $this->meta[ ExportPostType::META_TOTAL_ITEMS ] );
		self::assertSame( '1', $this->meta[ ExportPostType::META_TOTAL_BATCHES ] );
		self::assertSame( '1', $this->meta[ ExportPostType::META_PROCESSED_BATCHES ] );
		self::assertCount( 1, $this->dispatched );
		self::assertInstanceOf( FinalizeExportMessage::class, $this->dispatched[0] );
	}

	public function test_count_error_marks_export_failed_and_returns_error(): void {
		$error = new WP_Error( 'count_failed', 'Count failed' );

		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER => 'orders',
			ExportPostType::META_EXPORT_WRITER  => 'csv',
			ExportPostType::META_FILTERS        => '[]',
		];

		$adapter = $this->createMock( BatchExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );
		$adapter->method( 'count_items' )->willReturn( $error );

		$this->mock_wordpress_state( $adapter, $this->renderer() );

		self::assertSame( $error, $this->handler()->__invoke( new StartExportMessage( 42, 'csv' ) ) );
		self::assertSame( ExportStatus::FAILED, $this->meta[ ExportPostType::META_STATUS ] );
		self::assertSame( 'Count failed', $this->meta[ ExportPostType::META_ERROR_MESSAGE ] );
	}

	private function mock_wordpress_state( BatchExportAdapterInterface $adapter, ExportRendererInterface $renderer ): void {
		Functions\when( 'get_post_type' )->alias( static fn ( int $post_id ): string => 42 === $post_id ? ExportPostType::POST_TYPE : 'post' );
		Functions\when( 'get_post_meta' )->alias( fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? '' );
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed $value ) use ( $adapter, $renderer ): mixed {
				return match ( $hook ) {
					'storeaccountant_export_adapter' => [ $adapter ],
					'storeaccountant_export_renderer' => [ $renderer ],
					'storeaccountant_export_batch_size',
					'storeaccountant_export_field_provider',
					'storeaccountant_export_field_value_provider',
					'storeaccountant_export_field_value_mutator',
					'storeaccountant_export_attachment_provider' => $value,
					default => $value,
				};
			}
		);
	}

	private function mock_filesystem(): void {
		$uploads = sys_get_temp_dir() . '/storeaccountant-start-handler-' . uniqid( '', true );

		Functions\when( 'wp_upload_dir' )->alias( static fn (): array => [ 'basedir' => $uploads ] );
		Functions\when( 'wp_mkdir_p' )->alias( static fn ( string $path ): bool => is_dir( $path ) || mkdir( $path, 0777, true ) );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/\\' ) . '/' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			eval( 'namespace { function WP_Filesystem(): bool { return true; } }' );
		}

		$GLOBALS['wp_filesystem'] = new class() {
			public function put_contents( string $path, string $contents ): bool {
				if ( ! is_dir( dirname( $path ) ) ) {
					mkdir( dirname( $path ), 0777, true );
				}

				return false !== file_put_contents( $path, $contents );
			}
		};
	}

	private function batch_adapter( int|WP_Error $total_items ): BatchExportAdapterInterface {
		$adapter = $this->createMock( BatchExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );
		$adapter->method( 'count_items' )->willReturn( $total_items );
		$adapter->method( 'get_context' )->willReturn( new \StoreAccountant\Export\ExportContext( 'orders' ) );
		$adapter->method( 'get_additional_fields' )->willReturn( new FieldCollection() );
		$adapter->method( 'get_additional_values' )->willReturn( [] );
		$adapter->method( 'get_record_id' )->willReturn( '' );

		return $adapter;
	}

	private function renderer(): ExportRendererInterface {
		$renderer = $this->createMock( ExportRendererInterface::class );
		$renderer->method( 'get_id' )->willReturn( 'csv' );

		return $renderer;
	}

	private function handler(): StartExportMessageHandler {
		return new StartExportMessageHandler(
			$this->message_bus(),
			new ExportAdapterRegistry(),
			$this->repository(),
			new ExportFilterSelectionSerializer(),
			new ExportRendererRegistry(),
			new ExportDatasetBuilder(
				new FieldValueProviderRegistry(),
				new FieldValueMutatorRegistry(),
				new ExportFieldResolver( new FieldProviderRegistry(), new FieldMappingRepository() ),
				new ExportAttachmentProviderRegistry()
			),
			new BatchExportStore()
		);
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
}
