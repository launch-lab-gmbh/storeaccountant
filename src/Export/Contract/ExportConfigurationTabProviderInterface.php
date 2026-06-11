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

use WP_Post;
use StoreAccountant\Contract\RegistryItemInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides additional tabs for saved export configurations.
 */
interface ExportConfigurationTabProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether this provider supports the current configuration.
	 *
	 * @param WP_Post $configuration Export configuration post.
	 */
	public function supports( WP_Post $configuration ): bool;

	/**
	 * Gets additional tabs keyed by tab identifier.
	 *
	 * @param WP_Post $configuration Export configuration post.
	 *
	 * @return array<string, string>
	 */
	public function get_tabs( WP_Post $configuration ): array;

	/**
	 * Renders a supported tab.
	 *
	 * @param string  $tab           Active tab identifier.
	 * @param WP_Post $configuration Export configuration post.
	 * @param bool    $read_only     Whether the tab should be rendered read-only.
	 */
	public function render( string $tab, WP_Post $configuration, bool $read_only = false ): void;
}
