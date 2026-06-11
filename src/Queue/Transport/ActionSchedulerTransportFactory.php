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

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use function ltrim;
use function wp_parse_url;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates Action Scheduler Messenger transports.
 */
final readonly class ActionSchedulerTransportFactory implements TransportFactoryInterface {
	private const SCHEME = 'action_scheduler';

	/**
	 * {@inheritDoc}
	 */
	public function createTransport( string $dsn, array $options, SerializerInterface $serializer ): TransportInterface {
		$queue_name = $this->get_queue_name( $dsn );

		return new ActionSchedulerTransport( $queue_name, $serializer );
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( string $dsn, array $options ): bool {
		return self::SCHEME === (string) wp_parse_url( $dsn, PHP_URL_SCHEME );
	}

	/**
	 * Gets the queue name from the DSN.
	 *
	 * @param string $dsn Transport DSN.
	 */
	private function get_queue_name( string $dsn ): string {
		$host = (string) wp_parse_url( $dsn, PHP_URL_HOST );
		$path = ltrim( (string) wp_parse_url( $dsn, PHP_URL_PATH ), '/' );

		if ( '' !== $path ) {
			return $path;
		}

		return '' !== $host ? $host : 'exports';
	}
}
