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

namespace StoreAccountant\Tests\Unit\Queue;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
use StoreAccountant\Queue\QueueTransportRegistry;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests queue transport provider registry behavior.
 */
final class QueueTransportRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_queue_transport_hook_and_filters_by_type(): void {
		$provider = $this->provider( 'sync' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $provider ] );

		self::assertSame( [ 'sync' => $provider ], ( new QueueTransportRegistry() )->get_all() );
	}

	public function test_get_active_returns_configured_provider_when_available(): void {
		$sync             = $this->provider( 'sync' );
		$action_scheduler = $this->provider( 'action_scheduler' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ $sync, $action_scheduler ] );
		Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( 'action_scheduler' );
		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'action_scheduler' )
			->andReturn( 'action_scheduler' );

		self::assertSame( $action_scheduler, ( new QueueTransportRegistry() )->get_active() );
	}

	public function test_get_active_falls_back_to_first_provider_or_null(): void {
		$sync = $this->provider( 'sync' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ $sync ] );
		Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( 'missing' );
		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'missing' )
			->andReturn( 'missing' );

		self::assertSame( $sync, ( new QueueTransportRegistry() )->get_active() );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [] );
		Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( 'sync' );
		Functions\expect( 'sanitize_key' )
			->once()
			->with( 'sync' )
			->andReturn( 'sync' );

		self::assertNull( ( new QueueTransportRegistry() )->get_active() );
	}

	public function test_save_active_persists_only_registered_provider(): void {
		$sync = $this->provider( 'sync' );

		Functions\expect( 'sanitize_key' )
			->twice()
			->andReturnUsing( static fn ( string $id ): string => $id );
		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ $sync ] );
		Functions\expect( 'update_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync', false );

		$registry = new QueueTransportRegistry();
		$registry->save_active( 'sync' );
		$registry->save_active( 'missing' );

		self::assertTrue( true );
	}

	private function provider( string $id ): QueueTransportProviderInterface {
		$provider = $this->createMock( QueueTransportProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );

		return $provider;
	}
}
