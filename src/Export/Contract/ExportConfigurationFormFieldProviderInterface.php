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

namespace StoreAccountant\Export\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines additional fields for saved export configurations.
 */
interface ExportConfigurationFormFieldProviderInterface extends RegistryItemInterface {
	/**
	 * Renders additional configuration fields.
	 *
	 * @param array<string, mixed> $settings Current provider settings.
	 * @param bool                 $read_only Whether fields should be rendered read-only.
	 */
	public function render_fields( array $settings, bool $read_only = false ): void;

	/**
	 * Sanitizes submitted provider settings.
	 *
	 * @param array<string, mixed> $request Raw request data.
	 *
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $request ): array;

	/**
	 * Validates sanitized provider settings.
	 *
	 * @param array<string, mixed> $settings Sanitized provider settings.
	 *
	 * @return true|WP_Error
	 */
	public function validate_settings( array $settings ): true|WP_Error;
}
