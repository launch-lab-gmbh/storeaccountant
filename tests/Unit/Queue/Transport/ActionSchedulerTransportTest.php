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

namespace StoreAccountant\Tests\Unit\Queue\Transport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Tests Action Scheduler queue transport behavior.
 */
final class ActionSchedulerTransportTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_hooks_returns_all_registered_transport_hooks(): void {
		self::assertSame(
			[
				ActionSchedulerTransport::HOOK_EXPORT_START,
				ActionSchedulerTransport::HOOK_EXPORT_PROCESS_BATCH,
				ActionSchedulerTransport::HOOK_EXPORT_FINALIZE,
				ActionSchedulerTransport::HOOK_EXPORT_FINALIZE_ATTACHMENTS,
				ActionSchedulerTransport::HOOK_DEFAULT,
			],
			ActionSchedulerTransport::get_hooks()
		);
	}

	/**
	 * @param object              $message       Queue message.
	 * @param string              $expected_hook Expected Action Scheduler hook.
	 * @param array<string,mixed> $expected_args Expected extra args.
	 */
	#[DataProvider( 'message_hook_provider' )]
	public function test_send_queues_message_through_action_scheduler(
		object $message,
		string $expected_hook,
		array $expected_args
	): void {
		$envelope = new Envelope( $message );
		$encoded  = [
			'body'    => 'serialized-message',
			'headers' => [ 'type' => $message::class ],
		];

		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->expects( self::once() )
			->method( 'encode' )
			->with( $envelope )
			->willReturn( $encoded );

		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with(
				$expected_hook,
				[
					'queue_name'    => 'exports',
					'envelope'      => $encoded,
					'message_class' => $message::class,
				] + $expected_args,
				'storeaccountant'
			)
			->andReturn( 987 );
		Functions\expect( 'is_wp_error' )
			->once()
			->with( 987 )
			->andReturn( false );

		$result = ( new ActionSchedulerTransport( 'exports', $serializer ) )->send( $envelope );
		$stamp  = $result->last( TransportMessageIdStamp::class );

		self::assertInstanceOf( TransportMessageIdStamp::class, $stamp );
		self::assertSame( '987', $stamp->getId() );
	}

	public function test_send_throws_when_action_scheduler_rejects_message(): void {
		$message  = new \stdClass();
		$envelope = new Envelope( $message );

		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->method( 'encode' )->willReturn(
			[
				'body'    => 'serialized-message',
				'headers' => [],
			]
		);

		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with( ActionSchedulerTransport::HOOK_DEFAULT, Mockery::type( 'array' ), 'storeaccountant' )
			->andReturn( false );
		Functions\expect( 'is_wp_error' )
			->once()
			->with( false )
			->andReturn( false );

		$this->expectException( TransportException::class );
		$this->expectExceptionMessage( 'The message could not be queued through Action Scheduler.' );

		( new ActionSchedulerTransport( 'exports', $serializer ) )->send( $envelope );
	}

	public function test_get_ack_and_reject_are_noops_for_action_scheduler_transport(): void {
		$transport = new ActionSchedulerTransport( 'exports', $this->createMock( SerializerInterface::class ) );
		$envelope  = new Envelope( new StartExportMessage( 123 ) );

		self::assertSame( [], $transport->get() );

		$transport->ack( $envelope );
		$transport->reject( $envelope );

		self::assertTrue( true );
	}

	/**
	 * @return iterable<string,array{0:object,1:string,2:array<string,mixed>}>
	 */
	public static function message_hook_provider(): iterable {
		yield 'start export' => [
			new StartExportMessage( 123 ),
			ActionSchedulerTransport::HOOK_EXPORT_START,
			[ 'export_id' => 123 ],
		];

		yield 'process export batch' => [
			new ProcessExportBatchMessage( 456, 2, 100, 50 ),
			ActionSchedulerTransport::HOOK_EXPORT_PROCESS_BATCH,
			[ 'export_id' => 456 ],
		];

		yield 'finalize export' => [
			new FinalizeExportMessage( 789 ),
			ActionSchedulerTransport::HOOK_EXPORT_FINALIZE,
			[ 'export_id' => 789 ],
		];

		yield 'finalize export attachments' => [
			new FinalizeExportAttachmentsMessage( 789, 'csv', 'exports/export.zip', 0, 100, 200 ),
			ActionSchedulerTransport::HOOK_EXPORT_FINALIZE_ATTACHMENTS,
			[ 'export_id' => 789 ],
		];

		yield 'generic message' => [
			new \stdClass(),
			ActionSchedulerTransport::HOOK_DEFAULT,
			[],
		];
	}
}
