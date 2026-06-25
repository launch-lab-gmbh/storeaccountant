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

use Closure;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes Messenger messages immediately in the current PHP process.
 */
final readonly class SyncTransport implements TransportInterface {
	/**
	 * Initializes the transport.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Closure(): HandlersLocatorInterface $handlers_locator_factory Handler locator factory.
	 */
	public function __construct(
		private Closure $handlers_locator_factory
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function send( Envelope $envelope ): Envelope {
		$handlers = ( $this->handlers_locator_factory )();

		foreach ( $handlers->getHandlers( $envelope ) as $handler_descriptor ) {
			$handler = $handler_descriptor->getHandler();
			$handler( $envelope->getMessage() );
		}

		return $envelope;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get(): iterable {
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function ack( Envelope $envelope ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function reject( Envelope $envelope ): void {}
}
