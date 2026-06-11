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

namespace StoreAccountant\Tests\Unit\Export\Event;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Event\ExportEventDispatcher;
use StoreAccountant\Export\Event\ExportEvents;

/**
 * Tests export event dispatching.
 */
final class ExportEventDispatcherTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_dispatch_fires_wordpress_action_for_event_with_arguments(): void {
		Functions\expect( 'do_action' )
			->once()
			->with( ExportEvents::COMPLETED->value, 123, [ 'processed_items' => 5 ] );

		ExportEventDispatcher::dispatch( ExportEvents::COMPLETED, 123, [ 'processed_items' => 5 ] );

		self::assertTrue( true );
	}
}
