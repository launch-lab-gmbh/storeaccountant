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

use WP_Error;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use function is_array;
use function is_scalar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves dynamic filter settings to an immutable export snapshot.
 */
final readonly class ExportFilterSnapshotter {
	/**
	 * Initializes the snapshotter.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param PeriodProviderRegistry $period_providers Period provider registry.
	 */
	public function __construct(
		private PeriodProviderRegistry $period_providers
	) {}

	/**
	 * Creates export-time snapshots for filter selections.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, ExportFilterSelection> $selections Filter selections.
	 *
	 * @return array<int, ExportFilterSelection>|WP_Error
	 */
	public function snapshot( array $selections ): array|WP_Error {
		$snapshots = [];

		foreach ( $selections as $selection ) {
			$settings = $selection->settings;

			if ( isset( $settings['period_provider'], $settings['period'] ) && is_scalar( $settings['period_provider'] ) && is_array( $settings['period'] ) ) {
				$provider = $this->period_providers->get( sanitize_key( (string) $settings['period_provider'] ) );

				if ( null === $provider ) {
					return new WP_Error( 'storeaccountant_period_provider_unavailable', __( 'The configured period provider is unavailable.', 'storeaccountant' ) );
				}

				$period = $provider->resolve( $settings['period'] );

				if ( is_wp_error( $period ) ) {
					return $period;
				}

				$settings['resolved_period'] = [
					'start_at' => $period->start_at,
					'end_at'   => $period->end_at,
				];
			}

			$snapshots[] = new ExportFilterSelection( $selection->filter_id, $settings );
		}

		return $snapshots;
	}
}
