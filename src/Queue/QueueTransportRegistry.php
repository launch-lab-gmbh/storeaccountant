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

namespace StoreAccountant\Queue;

use StoreAccountant\Registry;
use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides configured queue transport providers.
 */
final readonly class QueueTransportRegistry extends Registry {
	public const OPTION_NAME          = 'storeaccountant_queue_transport_provider';
	private const DEFAULT_PROVIDER_ID = 'action_scheduler';

	/**
	 * Gets the active queue transport provider.
	 */
	public function get_active(): ?QueueTransportProviderInterface {
		$providers = $this->get_all();
		$active_id = sanitize_key( (string) get_option( self::OPTION_NAME, self::DEFAULT_PROVIDER_ID ) );

		if ( isset( $providers[ $active_id ] ) && $providers[ $active_id ] instanceof QueueTransportProviderInterface ) {
			return $providers[ $active_id ];
		}

		foreach ( $providers as $provider ) {
			if ( $provider instanceof QueueTransportProviderInterface ) {
				return $provider;
			}
		}

		return null;
	}

	/**
	 * Saves the active queue transport provider.
	 *
	 * @param string $id Queue transport provider ID.
	 */
	public function save_active( string $id ): void {
		$id = sanitize_key( $id );

		if ( isset( $this->get_all()[ $id ] ) ) {
			update_option( self::OPTION_NAME, $id, false );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_queue_transport_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return QueueTransportProviderInterface::class;
	}
}
