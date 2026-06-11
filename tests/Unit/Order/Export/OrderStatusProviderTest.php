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

namespace StoreAccountant\Tests\Unit\Order\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Order\Export\OrderStatusProvider;

/**
 * Tests WooCommerce order status normalization.
 */
final class OrderStatusProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_statuses_returns_non_empty_string_statuses_and_labels(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-completed' => 'Completed',
					'wc-failed'    => 'Failed',
					''             => 'Empty key',
					'wc-empty'     => '',
					123            => 'Numeric key',
					'wc-array'     => [ 'invalid' ],
				]
			);

		self::assertSame(
			[
				'wc-completed' => 'Completed',
				'wc-failed'    => 'Failed',
			],
			( new OrderStatusProvider() )->get_statuses()
		);
	}

	public function test_get_default_statuses_returns_available_status_keys(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-completed' => 'Completed',
					'wc-failed'    => 'Failed',
				]
			);

		self::assertSame( [ 'wc-completed', 'wc-failed' ], ( new OrderStatusProvider() )->get_default_statuses() );
	}

	public function test_sanitize_statuses_removes_unknown_duplicate_and_non_string_values(): void {
		Functions\expect( 'wc_get_order_statuses' )
			->once()
			->andReturn(
				[
					'wc-completed' => 'Completed',
					'wc-failed'    => 'Failed',
				]
			);

		Functions\when( 'wp_unslash' )->alias( static fn ( mixed $value ): mixed => $value );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $value ): string => strtolower( str_replace( ' ', '-', $value ) ) );

		self::assertSame(
			[ 'wc-completed', 'wc-failed' ],
			( new OrderStatusProvider() )->sanitize_statuses(
				[
					'wc-completed',
					'wc-completed',
					'WC Failed',
					'wc-unknown',
					123,
				]
			)
		);
	}

	public function test_sanitize_statuses_returns_empty_array_for_non_array_value(): void {
		self::assertSame( [], ( new OrderStatusProvider() )->sanitize_statuses( 'wc-completed' ) );
	}
}
