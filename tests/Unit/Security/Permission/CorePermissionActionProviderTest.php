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
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Security\Permission\CorePermissionActionProvider;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests built-in permission action registration.
 */
final class CorePermissionActionProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_permission_action_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_permission_action', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new CorePermissionActionProvider() )->register();

		self::assertTrue( true );
	}

	public function test_register_actions_adds_all_core_permission_actions_and_keeps_existing(): void {
		$actions = ( new CorePermissionActionProvider() )->register_actions( [ 'custom' => 'kept' ] );

		self::assertArrayHasKey( 'custom', $actions );
		self::assertArrayHasKey( PermissionActionIds::ACCESS_ADMIN, $actions );
		self::assertArrayHasKey( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING, $actions );
		self::assertSame( StoreAccountantCapabilities::ACCESS_ADMIN, $actions[ PermissionActionIds::ACCESS_ADMIN ]->get_capability() );
		self::assertSame( StoreAccountantCapabilities::EDIT_FIELD_MAPPING, $actions[ PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING ]->get_capability() );
		self::assertCount( 17, $actions );
	}
}
