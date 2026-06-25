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
 * Export lifecycle status values.
 */
final class ExportStatus {
	public const SCHEDULED  = 'scheduled';
	public const QUEUED     = 'queued';
	public const PROCESSING = 'processing';
	public const COMPLETED  = 'completed';
	public const FAILED     = 'failed';

	/**
	 * Checks whether a status is known.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $status Export status.
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, [ self::SCHEDULED, self::QUEUED, self::PROCESSING, self::COMPLETED, self::FAILED ], true );
	}

	/**
	 * Gets the translated admin label for a status.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $status Export status.
	 */
	public static function get_label( string $status ): string {
		return match ( $status ) {
			self::SCHEDULED => __( 'Scheduled', 'storeaccountant' ),
			self::QUEUED => __( 'Queued', 'storeaccountant' ),
			self::PROCESSING => __( 'Processing', 'storeaccountant' ),
			self::COMPLETED => __( 'Completed', 'storeaccountant' ),
			self::FAILED => __( 'Failed', 'storeaccountant' ),
			default => __( 'Unknown', 'storeaccountant' ),
		};
	}
}
