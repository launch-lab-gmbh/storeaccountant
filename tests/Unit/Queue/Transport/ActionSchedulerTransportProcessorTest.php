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
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Tests Action Scheduler transport callback processing.
 */
final class ActionSchedulerTransportProcessorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_processor_to_all_transport_hooks(): void {
		foreach ( ActionSchedulerTransport::get_hooks() as $hook ) {
			Functions\expect( 'add_action' )
				->once()
				->with( $hook, Mockery::type( 'array' ), 10, 3 );
		}

		( new ActionSchedulerTransportProcessor(
			$this->createMock( SerializerInterface::class ),
			$this->createMock( HandlersLocatorInterface::class )
		) )->register();

		self::assertTrue( true );
	}

	public function test_process_decodes_envelope_and_invokes_located_handlers(): void {
		$encoded  = [
			'body'    => 'serialized-message',
			'headers' => [],
		];
		$message  = new StartExportMessage( 42 );
		$handled  = [];
		$envelope = new Envelope( $message );

		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->expects( self::once() )
			->method( 'decode' )
			->with( $encoded )
			->willReturn( $envelope );

		$handlers = $this->createMock( HandlersLocatorInterface::class );
		$handlers->expects( self::once() )
			->method( 'getHandlers' )
			->with( $envelope )
			->willReturn(
				[
					new class(
						static function ( StartExportMessage $message ) use ( &$handled ): void {
							$handled[] = $message->export_id;
						}
					) {
						public function __construct(
							private readonly mixed $handler
						) {}

						public function getHandler(): mixed {
							return $this->handler;
						}
					},
				]
			);

		( new ActionSchedulerTransportProcessor( $serializer, $handlers ) )->process( 'exports', $encoded, StartExportMessage::class );

		self::assertSame( [ 42 ], $handled );
	}

	public function test_process_accepts_action_scheduler_payload_array(): void {
		$encoded = [
			'body'    => 'serialized-message',
			'headers' => [],
		];
		$message = new StartExportMessage( 42 );

		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->expects( self::once() )
			->method( 'decode' )
			->with( $encoded )
			->willReturn( new Envelope( $message ) );

		$handlers = $this->createMock( HandlersLocatorInterface::class );
		$handlers->expects( self::once() )
			->method( 'getHandlers' )
			->willReturn( [] );

		( new ActionSchedulerTransportProcessor( $serializer, $handlers ) )->process( [ 'envelope' => $encoded ] );
	}

	public function test_process_ignores_invalid_payloads(): void {
		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->expects( self::never() )->method( 'decode' );

		$handlers = $this->createMock( HandlersLocatorInterface::class );
		$handlers->expects( self::never() )->method( 'getHandlers' );

		( new ActionSchedulerTransportProcessor( $serializer, $handlers ) )->process( 'exports', 'invalid', StartExportMessage::class );
	}
}
