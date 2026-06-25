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

namespace StoreAccountant\Queue\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches StoreAccountant queue messages through Symfony's message bus contract.
 */
final readonly class QueueMessageBus implements MessageBusInterface {
	/**
	 * Initializes the bus.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param TransportInterface $transport Messenger transport.
	 */
	public function __construct(
		private TransportInterface $transport
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function dispatch( object $message, array $stamps = [] ): Envelope {
		return $this->transport->send( new Envelope( $message, $stamps ) );
	}
}
