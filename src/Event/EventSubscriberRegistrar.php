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

namespace StoreAccountant\Event;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Event\Contract\EventSubscriberInterface;
use function add_action;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers event subscribers on WordPress action hooks.
 */
final readonly class EventSubscriberRegistrar implements HookRegistrarInterface {
	/**
	 * Event subscribers.
	 *
	 * @var array<int, EventSubscriberInterface>
	 */
	private array $subscribers;

	public function __construct( EventSubscriberInterface ...$subscribers ) {
		$this->subscribers = $subscribers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		foreach ( $this->subscribers as $subscriber ) {
			foreach ( $subscriber::get_subscribed_events() as $hook => $handlers ) {
				foreach ( $handlers as $handler ) {
					$method        = $handler[0];
					$priority      = $handler[1] ?? 10;
					$accepted_args = $handler[2] ?? 1;

					add_action( $hook, [ $subscriber, $method ], $priority, $accepted_args );
				}
			}
		}
	}
}
