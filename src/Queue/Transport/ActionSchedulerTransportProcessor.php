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

namespace StoreAccountant\Queue\Transport;

use StoreAccountant\Contract\HookRegistrarInterface;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Receives Action Scheduler transport callbacks and invokes registered handlers.
 */
final readonly class ActionSchedulerTransportProcessor implements HookRegistrarInterface {
	/**
	 * Initializes the processor.
	 *
	 * @param SerializerInterface      $serializer Messenger transport serializer.
	 * @param HandlersLocatorInterface $handlers   Messenger handler locator.
	 */
	public function __construct(
		private SerializerInterface $serializer,
		private HandlersLocatorInterface $handlers
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		foreach ( ActionSchedulerTransport::get_hooks() as $hook ) {
			add_action( $hook, [ $this, 'process' ], 10, 3 );
		}
	}

	/**
	 * Processes one encoded Messenger envelope.
	 *
	 * @param mixed $queue_name    Queue name.
	 * @param mixed $encoded       Encoded envelope.
	 * @param mixed $message_class Message class.
	 */
	public function process( mixed $queue_name = null, mixed $encoded = null, mixed $message_class = null ): void {
		if ( is_array( $queue_name ) && isset( $queue_name['envelope'] ) ) {
			$encoded = $queue_name['envelope'];
		}

		if ( ! is_array( $encoded ) ) {
			return;
		}

		$envelope = $this->serializer->decode( $encoded );

		foreach ( $this->handlers->getHandlers( $envelope ) as $handler_descriptor ) {
			$handler = $handler_descriptor->getHandler();
			$handler( $envelope->getMessage() );
		}
	}
}
