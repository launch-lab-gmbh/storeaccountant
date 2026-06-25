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

namespace StoreAccountant\Export\Event;

use function do_action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dispatches public export lifecycle events.
 */
final class ExportEventDispatcher {
	/**
	 * Fires an export lifecycle event.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportEvents $event Export event hook.
	 * @param mixed        ...$args Event arguments.
	 */
	public static function dispatch( ExportEvents $event, mixed ...$args ): void {
		/**
		 * Fires one StoreAccountant export lifecycle event.
		 *
		 * @since 1.0.0
		 *
		 * @param mixed ...$args Event-specific arguments.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- ExportEvents enum values define the prefixed public hook names.
		do_action( $event->value, ...$args );
	}
}
