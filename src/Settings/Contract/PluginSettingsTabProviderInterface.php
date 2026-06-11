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

namespace StoreAccountant\Settings\Contract;

use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides additional tabs for the plugin settings page.
 */
interface PluginSettingsTabProviderInterface extends RegistryItemInterface {
	/**
	 * Gets additional tabs keyed by tab identifier.
	 *
	 * @return array<string, string>
	 */
	public function get_tabs(): array;

	/**
	 * Renders a supported tab.
	 *
	 * @param string $tab Active tab identifier.
	 */
	public function render( string $tab ): void;

	/**
	 * Saves a supported tab.
	 *
	 * @param string               $tab     Active tab identifier.
	 * @param array<string, mixed> $request Request data.
	 */
	public function save( string $tab, array $request ): void;
}
