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

namespace StoreAccountant\Tests\Unit\Security\Permission;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Security\Permission\Contract\PermissionActionInterface;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests permission action registry behavior.
 */
final class PermissionActionRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_permission_action_hook_and_filters_by_type(): void {
		$action = $this->createMock( PermissionActionInterface::class );
		$action->method( 'get_id' )->willReturn( 'export.list' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $action ] );

		self::assertSame( [ 'export.list' => $action ], ( new PermissionActionRegistry() )->get_all() );
	}
}
