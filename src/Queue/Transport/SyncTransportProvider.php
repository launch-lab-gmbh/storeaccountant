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
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the built-in synchronous queue transport.
 */
final readonly class SyncTransportProvider implements QueueTransportProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'sync';

	/**
	 * Initializes the provider.
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
	public function register(): void {
		add_filter(
			'storeaccountant_queue_transport_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY - 1
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_label(): string {
		return __( 'Synchronous', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_description(): string {
		return __( 'Processes StoreAccountant queue jobs immediately in the current request or cron run. This is useful for small exports, but large exports can take a long time. Use the Action Scheduler transport for larger exports.', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_dsn(): string {
		return 'sync://exports';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports_manual_loopback(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function create_transport( SerializerInterface $serializer ): TransportInterface {
		return new SyncTransport( $this->handlers_locator_factory );
	}
}
