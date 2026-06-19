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

namespace StoreAccountant\Diagnostic;

use function get_option;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and stores diagnostic logging settings.
 */
final readonly class DiagnosticSettings {
	private const OPTION_ENABLED = 'storeaccountant_diagnostic_logging_enabled';

	/**
	 * Checks whether diagnostic incident logging is enabled.
	 */
	public function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * Saves whether diagnostic incident logging is enabled.
	 */
	public function save_enabled( bool $enabled ): void {
		update_option( self::OPTION_ENABLED, $enabled ? '1' : '0', false );
	}
}
