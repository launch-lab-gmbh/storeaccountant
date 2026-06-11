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

namespace StoreAccountant\Tests\Unit\Export\Field\Mutator;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the field value mutator registry.
 */
final class FieldValueMutatorRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_mutator_hook_and_accepts_only_mutators(): void {
		$mutator = $this->createMock( FieldValueMutatorInterface::class );
		$mutator->method( 'get_id' )->willReturn( 'amount' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_field_value_mutator', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-mutator' ),
					$mutator,
				]
			);

		self::assertSame( [ 'amount' => $mutator ], ( new FieldValueMutatorRegistry() )->get_all() );
	}
}
