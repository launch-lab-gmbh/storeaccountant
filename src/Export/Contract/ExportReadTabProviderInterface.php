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
 * Provides tabs for saved export read views.
 */
interface ExportReadTabProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether this provider supports the current export.
	 *
	 * @param WP_Post $export Saved export post.
	 */
	public function supports( WP_Post $export ): bool;

	/**
	 * Gets tabs keyed by tab identifier.
	 *
	 * @param WP_Post $export Saved export post.
	 *
	 * @return array<string, string>
	 */
	public function get_tabs( WP_Post $export ): array;

	/**
	 * Renders a supported tab.
	 *
	 * @param string  $tab    Active tab identifier.
	 * @param WP_Post $export Saved export post.
	 */
	public function render( string $tab, WP_Post $export ): void;
}
