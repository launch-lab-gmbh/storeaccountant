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

namespace StoreAccountant\Export\Filter;

use function array_values;
use function is_array;
use function is_string;
use function json_decode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializes export filter selections for post meta.
 */
final readonly class ExportFilterSelectionSerializer {
	/**
	 * Encodes filter selections as JSON.
	 *
	 * @param array<int, ExportFilterSelection> $selections Filter selections.
	 */
	public function encode( array $selections ): string {
		$items = [];

		foreach ( $selections as $selection ) {
			$items[] = [
				'filter_id' => $selection->filter_id,
				'settings'  => $selection->settings,
			];
		}

		$json = wp_json_encode( $items );

		return is_string( $json ) ? $json : '[]';
	}

	/**
	 * Decodes filter selections from JSON.
	 *
	 * @return array<int, ExportFilterSelection>
	 */
	public function decode( string $json ): array {
		$items = json_decode( $json, true );

		if ( ! is_array( $items ) ) {
			return [];
		}

		$selections = [];

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['filter_id'] ) || ! is_string( $item['filter_id'] ) ) {
				continue;
			}

			$settings     = isset( $item['settings'] ) && is_array( $item['settings'] ) ? $item['settings'] : [];
			$selections[] = new ExportFilterSelection( sanitize_key( $item['filter_id'] ), $settings );
		}

		return array_values( $selections );
	}
}
