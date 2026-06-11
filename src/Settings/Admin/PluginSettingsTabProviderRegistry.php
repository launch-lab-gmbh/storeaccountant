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

namespace StoreAccountant\Settings\Admin;

use StoreAccountant\Settings\Contract\PluginSettingsTabProviderInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered plugin settings tab providers.
 */
final readonly class PluginSettingsTabProviderRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_plugin_settings_tab_provider';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return PluginSettingsTabProviderInterface::class;
	}
}
