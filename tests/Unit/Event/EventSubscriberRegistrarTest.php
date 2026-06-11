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

namespace StoreAccountant\Tests\Unit\Event;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Event\Contract\EventSubscriberInterface;
use StoreAccountant\Event\EventSubscriberRegistrar;

/**
 * Tests event subscriber WordPress hook registration.
 */
final class EventSubscriberRegistrarTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_actions_for_all_subscribed_handlers(): void {
		$subscriber = new class() implements EventSubscriberInterface {
			public static function get_subscribed_events(): array {
				return [
					'storeaccountant_first_event'  => [
						[ 'handle_first', 20, 3 ],
					],
					'storeaccountant_second_event' => [
						[ 'handle_second' ],
					],
				];
			}

			public function handle_first(): void {}

			public function handle_second(): void {}
		};

		Functions\expect( 'add_action' )
			->once()
			->with( 'storeaccountant_first_event', [ $subscriber, 'handle_first' ], 20, 3 );
		Functions\expect( 'add_action' )
			->once()
			->with( 'storeaccountant_second_event', [ $subscriber, 'handle_second' ], 10, 1 );

		( new EventSubscriberRegistrar( $subscriber ) )->register();

		self::assertTrue( true );
	}

	public function test_register_with_no_subscribers_does_not_add_actions(): void {
		Functions\expect( 'add_action' )->never();

		( new EventSubscriberRegistrar() )->register();

		self::assertTrue( true );
	}
}
