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
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\Handler\FinalizeExportMessageHandler;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\QueuedExportFinalizer;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\StorageAdapterRegistry;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use WP_Error;

/**
 * Tests queued export finalization handler guards and errors.
 */
final class FinalizeExportMessageHandlerTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->meta = [];

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-07 16:00:00' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => $value );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'do_action' )->justReturn();
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_invoke_returns_true_without_finalizing_completed_or_incomplete_exports(): void {
		$this->meta = [
			ExportPostType::META_STATUS            => ExportStatus::COMPLETED,
			ExportPostType::META_TOTAL_BATCHES     => '2',
			ExportPostType::META_PROCESSED_BATCHES => '1',
			ExportPostType::META_TOTAL_ITEMS       => '2',
			ExportPostType::META_PROCESSED_ITEMS   => '1',
		];

		$this->mock_meta();
		Functions\when( 'get_post_type' )->alias( static fn (): string => ExportPostType::POST_TYPE );

		self::assertTrue( $this->handler()->__invoke( new FinalizeExportMessage( 42, 'csv' ) ) );

		$this->meta[ ExportPostType::META_STATUS ] = ExportStatus::PROCESSING;
		self::assertTrue( $this->handler()->__invoke( new FinalizeExportMessage( 42, 'csv' ) ) );
		self::assertSame( ExportStatus::PROCESSING, $this->meta[ ExportPostType::META_STATUS ] );
	}

	public function test_invalid_message_returns_wordpress_error(): void {
		Functions\when( 'get_post_type' )->alias( static fn (): string => 'post' );

		$result = $this->handler()->__invoke( new FinalizeExportMessage( 42, 'csv' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_export_finalize_message_invalid', $result->get_error_code() );
	}

	private function mock_meta(): void {
		Functions\when( 'get_post_meta' )->alias( fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? '' );
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
	}

	private function handler(): FinalizeExportMessageHandler {
		$repository = new ExportRepository(
			new ExportFilterSelectionSerializer(),
			new DownloadPasswordManager( new ReversibleCrypto() )
		);

		return new FinalizeExportMessageHandler(
			$this->message_bus(),
			new QueuedExportFinalizer(
				new BatchExportStore(),
				new StorageAdapterRegistry(),
				new ExportAdapterRegistry(),
				new ExportRendererRegistry(),
				$repository,
				new ExportStoragePathGenerator( new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' ) ),
				new ExportFilterSelectionSerializer()
			),
			$repository
		);
	}

	private function message_bus(): MessageBusInterface {
		return new class() implements MessageBusInterface {
			public function dispatch( object $message, array $stamps = [] ): Envelope {
				return new Envelope( $message, $stamps );
			}
		};
	}
}
