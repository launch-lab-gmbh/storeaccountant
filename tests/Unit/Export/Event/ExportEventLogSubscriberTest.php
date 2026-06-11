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

namespace StoreAccountant\Tests\Unit\Export\Event;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Event\ExportEventLogSubscriber;
use StoreAccountant\Export\Event\ExportEvents;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Security\ReversibleCrypto;

/**
 * Tests export event log subscriber messages.
 */
final class ExportEventLogSubscriberTest extends TestCase {
	/** @var array<int, array<string, mixed>> */
	private array $entries = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'get_post_meta' )->alias( fn (): array => $this->entries );
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				if ( ExportPostType::META_LOG_ENTRIES === $key ) {
					$this->entries = $value;
				}
			}
		);
		Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-06 12:00:00' );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_subscribed_events_lists_expected_export_events(): void {
		$events = ExportEventLogSubscriber::get_subscribed_events();

		foreach ( ExportEvents::cases() as $event ) {
			self::assertArrayHasKey( $event->value, $events );
		}

		self::assertSame( [ 'log_entry', 10, 5 ], $events[ ExportEvents::LOG_ENTRY->value ][0] );
		self::assertSame( [ 'log_failed', 10, 5 ], $events[ ExportEvents::FAILED->value ][0] );
	}

	public function test_log_methods_write_expected_status_and_progress_messages(): void {
		$subscriber = $this->subscriber();

		$subscriber->log_queued( 123 );
		$subscriber->log_batch_processed( 123, [ 'processed_items' => 0 ] );
		$subscriber->log_finalization_queued( 123, [ 'all_batches_processed' => true ] );
		$subscriber->log_completed( 123 );

		self::assertSame( 'Export queued for background processing.', $this->entries[0]['message'] );
		self::assertSame( 'No export items found; empty batch stored.', $this->entries[1]['message'] );
		self::assertSame( 'All export batches processed; finalization queued.', $this->entries[2]['message'] );
		self::assertSame( 'success', $this->entries[3]['level'] );
		self::assertSame( 'Export completed.', $this->entries[3]['message'] );
	}

	public function test_log_entry_and_failed_events_include_context_and_exception_details(): void {
		$exception  = new RuntimeException( 'Renderer failed' );
		$subscriber = $this->subscriber();

		$subscriber->log_entry( 123, 'warning', 'Custom message', [ 'batch' => 2 ], $exception );
		$subscriber->log_failed( 123, 'Public error', '', [ 'phase' => 'render' ], $exception );

		self::assertSame( 'warning', $this->entries[0]['level'] );
		self::assertSame( 'Custom message', $this->entries[0]['message'] );
		self::assertSame( [ 'batch' => 2 ], $this->entries[0]['context'] );
		self::assertSame( RuntimeException::class, $this->entries[0]['exception']['class'] );
		self::assertSame( 'Renderer failed', $this->entries[0]['exception']['message'] );

		self::assertSame( 'error', $this->entries[1]['level'] );
		self::assertSame( 'Public error', $this->entries[1]['message'] );
		self::assertSame( [ 'phase' => 'render' ], $this->entries[1]['context'] );
	}

	public function test_failed_event_prefers_log_message_when_provided(): void {
		$this->subscriber()->log_failed( 123, 'Public error', 'Technical log message' );

		self::assertSame( 'Technical log message', $this->entries[0]['message'] );
	}

	private function subscriber(): ExportEventLogSubscriber {
		return new ExportEventLogSubscriber(
			new ExportRepository(
				new ExportFilterSelectionSerializer(),
				new DownloadPasswordManager( new ReversibleCrypto() )
			)
		);
	}
}
