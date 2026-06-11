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

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Queue\Transport\SyncTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;

/**
 * Tests synchronous queue transport behavior.
 */
final class SyncTransportTest extends TestCase {
	public function test_send_invokes_all_located_handlers_with_message(): void {
		$message  = new StartExportMessage( 123 );
		$envelope = new Envelope( $message );
		$handled  = [];

		$transport = new SyncTransport(
			function () use ( &$handled ): HandlersLocatorInterface {
				return new class(
				static function ( StartExportMessage $message ) use ( &$handled ): void {
					$handled[] = [ 'first', $message->export_id ];
				},
				static function ( StartExportMessage $message ) use ( &$handled ): void {
					$handled[] = [ 'second', $message->export_id ];
				}
				) implements HandlersLocatorInterface {
					/**
					 * @var array<int, callable>
					 */
					private readonly array $handlers;

					/**
					 * @param callable ...$handlers Located message handlers.
					 */
					public function __construct(
						callable ...$handlers
					) {
						$this->handlers = $handlers;
					}

					/**
					 * {@inheritDoc}
					 */
					public function getHandlers( Envelope $envelope ): iterable {
						foreach ( $this->handlers as $handler ) {
							yield new class( $handler ) {
								public function __construct(
									private readonly mixed $handler
								) {}

								public function getHandler(): mixed {
									return $this->handler;
								}
							};
						}
					}
				};
			}
		);

		self::assertSame( $envelope, $transport->send( $envelope ) );
		self::assertSame( [ [ 'first', 123 ], [ 'second', 123 ] ], $handled );
	}

	public function test_get_ack_and_reject_are_noops_for_synchronous_transport(): void {
		$transport = new SyncTransport(
			static fn (): HandlersLocatorInterface => new class() implements HandlersLocatorInterface {
				public function getHandlers( Envelope $envelope ): iterable {
					return [];
				}
			}
		);
		$envelope  = new Envelope( new StartExportMessage( 123 ) );

		self::assertSame( [], $transport->get() );

		$transport->ack( $envelope );
		$transport->reject( $envelope );

		self::assertTrue( true );
	}
}
