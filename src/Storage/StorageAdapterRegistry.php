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

namespace StoreAccountant\Storage;

use StoreAccountant\Registry;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use function array_filter;
use function array_key_first;
use function array_intersect;
use function array_keys;
use function array_values;
use function count;
use function get_option;
use function in_array;
use function is_array;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides configured storage adapters.
 */
final readonly class StorageAdapterRegistry extends Registry {
	private const OPTION_NAME = 'storeaccountant_enabled_storage_adapters';

	/**
	 * Gets enabled storage adapters.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, StorageAdapterInterface>
	 */
	public function get_enabled(): array {
		$registered_adapters = $this->get_all();
		$enabled             = get_option( self::OPTION_NAME, null );

		if ( ! is_array( $enabled ) ) {
			return $registered_adapters;
		}

		$enabled = array_values( array_filter( $enabled, 'is_string' ) );

		return array_filter(
			$registered_adapters,
			static fn ( StorageAdapterInterface $adapter ): bool => in_array( $adapter->get_id(), $enabled, true )
		);
	}

	/**
	 * Checks whether a storage adapter is enabled.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $id storage adapter identifier.
	 */
	public function is_enabled( string $id ): bool {
		return isset( $this->get_enabled()[ $id ] );
	}

	/**
	 * Saves enabled storage adapters.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, string> $enabled Enabled storage adapter identifiers.
	 */
	public function save_enabled( array $enabled ): void {
		$registered_adapters = $this->get_all();

		if ( 1 === count( $registered_adapters ) ) {
			update_option( self::OPTION_NAME, [ (string) array_key_first( $registered_adapters ) ], false );
			return;
		}

		$known_enabled = array_values( array_intersect( $enabled, array_keys( $registered_adapters ) ) );

		update_option( self::OPTION_NAME, $known_enabled, false );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_storage_adapter';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return StorageAdapterInterface::class;
	}
}
