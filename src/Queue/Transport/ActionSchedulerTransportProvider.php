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
use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the built-in Action Scheduler queue transport.
 */
final readonly class ActionSchedulerTransportProvider implements QueueTransportProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'action_scheduler';

	private const QUEUE_NAME = 'exports';

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
			HookRegistrarInterface::DEFAULT_PRIORITY
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
		return __( 'Action Scheduler', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_description(): string {
		return __( 'Uses WooCommerce Action Scheduler to process StoreAccountant queue jobs in the background. This is recommended for larger exports.', 'storeaccountant' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_dsn(): string {
		return 'action_scheduler://' . self::QUEUE_NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports_manual_loopback(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function create_transport( SerializerInterface $serializer ): TransportInterface {
		return new ActionSchedulerTransport( self::QUEUE_NAME, $serializer );
	}
}
