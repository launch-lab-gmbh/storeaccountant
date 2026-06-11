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

namespace StoreAccountant\Tests\Unit\Queue\Messenger;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Queue\Messenger\QueueMessageBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Tests the StoreAccountant Symfony message bus adapter.
 */
final class QueueMessageBusTest extends TestCase {
	public function test_dispatch_sends_envelope_through_transport_and_returns_result(): void {
		$message         = new StartExportMessage( 42 );
		$stamp           = new class() implements StampInterface {};
		$return_envelope = new Envelope( new StartExportMessage( 99 ) );
		$transport       = $this->createMock( TransportInterface::class );

		$transport
			->expects( self::once() )
			->method( 'send' )
			->with(
				self::callback(
					static function ( Envelope $envelope ) use ( $message, $stamp ): bool {
						return $message === $envelope->getMessage()
							&& [ $stamp ] === $envelope->all( $stamp::class );
					}
				)
			)
			->willReturn( $return_envelope );

		$bus = new QueueMessageBus( $transport );

		self::assertSame( $return_envelope, $bus->dispatch( $message, [ $stamp ] ) );
	}
}
