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

namespace StoreAccountant\Order\Tax;

use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered WooCommerce order tax field providers.
 */
final readonly class OrderTaxFieldProviderRegistry extends Registry {
	/**
	 * Gets the selected tax field provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $provider_id Provider identifier.
	 */
	public function get_provider( string $provider_id ): ?OrderTaxFieldProviderInterface {
		$provider = $this->get( $provider_id );

		if ( null !== $provider ) {
			return $provider;
		}

		return $this->get( ExtendedOrderTaxFieldProvider::PROVIDER_ID );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_order_tax_field_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return OrderTaxFieldProviderInterface::class;
	}
}
