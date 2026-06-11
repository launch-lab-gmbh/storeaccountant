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
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests permission action checks.
 */
final class PermissionCheckerTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_can_allows_wordpress_options_managers_without_registry_lookup(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );
		Functions\expect( 'apply_filters' )->never();

		self::assertTrue( ( new PermissionChecker( new PermissionActionRegistry() ) )->can( 'anything' ) );
	}

	public function test_can_returns_false_for_unknown_action(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [] );

		self::assertFalse( ( new PermissionChecker( new PermissionActionRegistry() ) )->can( 'missing' ) );
	}

	public function test_can_requires_admin_access_for_non_access_admin_actions(): void {
		$action = $this->action( PermissionActionIds::EXPORT_LIST, StoreAccountantCapabilities::READ_EXPORTS );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [ $action ] );
		Functions\expect( 'current_user_can' )
			->once()
			->with( StoreAccountantCapabilities::ACCESS_ADMIN )
			->andReturn( false );

		self::assertFalse( ( new PermissionChecker( new PermissionActionRegistry() ) )->can( PermissionActionIds::EXPORT_LIST ) );
	}

	public function test_can_checks_action_capability_when_admin_access_is_available(): void {
		$action = $this->action( PermissionActionIds::EXPORT_VIEW, StoreAccountantCapabilities::VIEW_EXPORT );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [ $action ] );
		Functions\expect( 'current_user_can' )
			->once()
			->with( StoreAccountantCapabilities::ACCESS_ADMIN )
			->andReturn( true );
		Functions\expect( 'current_user_can' )
			->once()
			->with( StoreAccountantCapabilities::VIEW_EXPORT, 123 )
			->andReturn( true );

		self::assertTrue( ( new PermissionChecker( new PermissionActionRegistry() ) )->can( PermissionActionIds::EXPORT_VIEW, 123 ) );
	}

	public function test_get_capability_returns_registered_capability_or_fallback(): void {
		$action = $this->action( PermissionActionIds::EXPORT_CREATE, StoreAccountantCapabilities::CREATE_EXPORTS );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [ $action ] );

		self::assertSame(
			StoreAccountantCapabilities::CREATE_EXPORTS,
			( new PermissionChecker( new PermissionActionRegistry() ) )->get_capability( PermissionActionIds::EXPORT_CREATE, 'fallback' )
		);

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_permission_action', [] )
			->andReturn( [] );

		self::assertSame(
			'fallback',
			( new PermissionChecker( new PermissionActionRegistry() ) )->get_capability( PermissionActionIds::EXPORT_CREATE, 'fallback' )
		);
	}

	private function action( string $id, string $capability ): PermissionActionInterface {
		$action = $this->createMock( PermissionActionInterface::class );
		$action->method( 'get_id' )->willReturn( $id );
		$action->method( 'get_capability' )->willReturn( $capability );

		return $action;
	}
}
