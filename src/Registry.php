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

namespace StoreAccountant;

use StoreAccountant\Contract\RegistryInterface;
use StoreAccountant\Contract\RegistryItemInterface;
use function is_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides shared hook-backed registry behavior.
 */
abstract readonly class Registry implements RegistryInterface {
	/**
	 * {@inheritDoc}
	 */
	public function get( string $id ): ?RegistryItemInterface {
		return $this->get_all()[ $id ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_all(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Registry subclasses provide storeaccountant-prefixed extension hooks.
		$items = apply_filters( $this->get_hook_name(), [] );

		if ( ! is_array( $items ) ) {
			return [];
		}

		$type        = $this->get_type();
		$items_by_id = [];

		foreach ( $items as $item ) {
			if ( ! $item instanceof RegistryItemInterface || ! $item instanceof $type || '' === $item->get_id() ) {
				continue;
			}

			unset( $items_by_id[ $item->get_id() ] );
			$items_by_id[ $item->get_id() ] = $item;
		}

		return $items_by_id;
	}

	/**
	 * Gets the WordPress hook that provides registry items.
	 */
	abstract protected function get_hook_name(): string;

	/**
	 * Gets the interface or class name accepted by the registry.
	 *
	 * @return class-string<RegistryItemInterface>
	 */
	abstract protected function get_type(): string;
}
