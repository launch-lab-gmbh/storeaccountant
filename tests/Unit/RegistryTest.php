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

namespace StoreAccountant\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Tests\Unit\Doubles\OtherRegistryItem;
use StoreAccountant\Tests\Unit\Doubles\TestRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the hook-backed registry base behavior.
 */
final class RegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_returns_item_by_id(): void {
		$item = new TestRegistryItem( 'foo' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn( [ $item ] );

		$registry = new TestRegistry();

		self::assertSame( $item, $registry->get( 'foo' ) );
	}

	public function test_get_returns_null_for_unknown_id(): void {
		$item = new TestRegistryItem( 'foo' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn( [ $item ] );

		$registry = new TestRegistry();

		self::assertNull( $registry->get( 'bar' ) );
	}

	public function test_get_all_returns_empty_array_when_filter_result_is_not_array(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn( 'invalid' );

		$registry = new TestRegistry();

		self::assertSame( [], $registry->get_all() );
	}

	public function test_get_all_ignores_invalid_items(): void {
		$valid_item         = new TestRegistryItem( 'valid' );
		$item_with_empty_id = new TestRegistryItem( '' );
		$wrong_type_item    = new OtherRegistryItem( 'other' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn(
				[
					'not-an-object',
					new \stdClass(),
					$item_with_empty_id,
					$wrong_type_item,
					$valid_item,
				]
			);

		$registry = new TestRegistry();

		self::assertSame(
			[
				'valid' => $valid_item,
			],
			$registry->get_all()
		);
	}

	public function test_get_all_indexes_items_by_id(): void {
		$first  = new TestRegistryItem( 'first' );
		$second = new TestRegistryItem( 'second' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn( [ $first, $second ] );

		$registry = new TestRegistry();

		self::assertSame(
			[
				'first'  => $first,
				'second' => $second,
			],
			$registry->get_all()
		);
	}

	public function test_get_all_keeps_last_item_when_ids_are_duplicated(): void {
		$first  = new TestRegistryItem( 'duplicate' );
		$second = new TestRegistryItem( 'duplicate' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant/test_registry', [] )
			->andReturn( [ $first, $second ] );

		$registry = new TestRegistry();

		self::assertSame(
			[
				'duplicate' => $second,
			],
			$registry->get_all()
		);
	}
}
