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

namespace StoreAccountant\Invoice;

use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use StoreAccountant\Registry;
use function is_string;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered invoice plugin integrations.
 */
final readonly class InvoicePluginRegistry extends Registry {
	private const OPTION_NAME = 'storeaccountant_enabled_invoice_plugin';

	/**
	 * Gets active invoice plugin integrations.
	 *
	 * @return array<string, InvoicePluginInterface>
	 */
	public function get_available(): array {
		$available = [];

		foreach ( $this->get_all() as $plugin ) {
			if ( $plugin->is_active() ) {
				$available[ $plugin->get_id() ] = $plugin;
			}
		}

		return $available;
	}

	/**
	 * Gets the enabled invoice plugin integration.
	 */
	public function get_enabled(): ?InvoicePluginInterface {
		$plugin_id = get_option( self::OPTION_NAME, '' );

		if ( ! is_string( $plugin_id ) || '' === $plugin_id ) {
			return null;
		}

		$plugin = $this->get( $plugin_id );

		if ( null !== $plugin && $plugin->is_active() ) {
			return $plugin;
		}

		return null;
	}

	/**
	 * Checks whether the invoice plugin is enabled.
	 *
	 * @param string $plugin_id Invoice plugin ID.
	 */
	public function is_enabled( string $plugin_id ): bool {
		$plugin = $this->get_enabled();

		return null !== $plugin && $plugin_id === $plugin->get_id();
	}

	/**
	 * Saves the enabled invoice plugin integration.
	 *
	 * @param string $plugin_id Invoice plugin ID.
	 */
	public function save_enabled( string $plugin_id ): void {
		if ( '' === $plugin_id || ! isset( $this->get_available()[ $plugin_id ] ) ) {
			delete_option( self::OPTION_NAME );

			return;
		}

		update_option( self::OPTION_NAME, $plugin_id, false );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_invoice_plugin';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return InvoicePluginInterface::class;
	}
}
