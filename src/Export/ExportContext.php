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

namespace StoreAccountant\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries typed runtime context for export providers.
 */
final readonly class ExportContext {
	/**
	 * Initializes the export context.
	 *
	 * @param string               $export_type      Export adapter identifier.
	 * @param int                  $configuration_id Export configuration post ID.
	 * @param array<int, mixed>    $items            Source items.
	 * @param array<string, mixed> $values           Additional context values.
	 */
	public function __construct(
		public string $export_type,
		public int $configuration_id = 0,
		public array $items = [],
		public array $values = []
	) {}

	/**
	 * Gets an additional context value.
	 *
	 * @param string $key     Value key.
	 * @param mixed  $fallback Fallback value.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->values[ $key ] ?? $fallback;
	}

	/**
	 * Returns a copy with one additional context value.
	 *
	 * @param string $key   Value key.
	 * @param mixed  $value Context value.
	 */
	public function with( string $key, mixed $value ): self {
		$values         = $this->values;
		$values[ $key ] = $value;

		return new self( $this->export_type, $this->configuration_id, $this->items, $values );
	}
}
