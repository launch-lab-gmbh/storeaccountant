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

namespace StoreAccountant\Event\Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a service that subscribes methods to WordPress action hooks.
 */
interface EventSubscriberInterface {
	/**
	 * Gets subscribed event handlers.
	 *
	 * @return array<string, array<int, array{0:string, 1?:int, 2?:int}>>
	 */
	public static function get_subscribed_events(): array;
}
