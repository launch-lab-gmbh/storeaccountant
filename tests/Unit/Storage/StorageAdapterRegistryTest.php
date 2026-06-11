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

namespace StoreAccountant\Tests\Unit\Storage;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests storage adapter registry behavior.
 */
final class StorageAdapterRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_storage_adapter_hook_and_filters_by_type(): void {
		$adapter = $this->adapter( 'local' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $adapter ] );

		self::assertSame( [ 'local' => $adapter ], ( new StorageAdapterRegistry() )->get_all() );
	}

	public function test_get_enabled_returns_all_registered_adapters_without_saved_selection(): void {
		$local = $this->adapter( 'local' );
		$s3    = $this->adapter( 's3' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local, $s3 ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', null )
			->andReturn( null );

		self::assertSame(
			[
				'local' => $local,
				's3'    => $s3,
			],
			( new StorageAdapterRegistry() )->get_enabled()
		);
	}

	public function test_get_enabled_filters_saved_selection_to_registered_string_ids(): void {
		$local = $this->adapter( 'local' );
		$s3    = $this->adapter( 's3' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local, $s3 ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', null )
			->andReturn( [ 's3', 'missing', 123 ] );

		self::assertSame( [ 's3' => $s3 ], ( new StorageAdapterRegistry() )->get_enabled() );
	}

	public function test_is_enabled_reflects_enabled_adapter_selection(): void {
		$local = $this->adapter( 'local' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local ] );
		Functions\expect( 'get_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', null )
			->andReturn( [ 'local' ] );

		self::assertTrue( ( new StorageAdapterRegistry() )->is_enabled( 'local' ) );
	}

	public function test_save_enabled_persists_only_registered_ids(): void {
		$local = $this->adapter( 'local' );
		$s3    = $this->adapter( 's3' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local, $s3 ] );
		Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', [ 's3' ], false );

		( new StorageAdapterRegistry() )->save_enabled( [ 's3', 'missing' ] );

		self::assertTrue( true );
	}

	public function test_save_enabled_keeps_sole_registered_adapter_enabled(): void {
		$local = $this->adapter( 'local' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_storage_adapter', [] )
			->andReturn( [ $local ] );
		Functions\expect( 'update_option' )
			->once()
			->with( 'storeaccountant_enabled_storage_adapters', [ 'local' ], false );

		( new StorageAdapterRegistry() )->save_enabled( [] );

		self::assertTrue( true );
	}

	private function adapter( string $id ): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );

		return $adapter;
	}
}
