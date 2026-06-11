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

namespace StoreAccountant\Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a service that registers WordPress hooks.
 */
interface HookRegistrarInterface {
	public const DEFAULT_PRIORITY = 100;

	/**
	 * Registers WordPress hooks.
	 */
	public function register(): void;
}
