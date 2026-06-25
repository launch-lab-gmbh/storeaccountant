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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identifies a stored diagnostic incident.
 */
final readonly class DiagnosticIncident {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		public string $support_id,
		public string $file_name
	) {}
}
