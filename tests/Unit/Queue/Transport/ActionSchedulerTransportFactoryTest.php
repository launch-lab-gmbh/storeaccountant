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

namespace StoreAccountant\Tests\Unit\Queue\Transport;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Tests the Action Scheduler transport factory.
 */
final class ActionSchedulerTransportFactoryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_supports_accepts_action_scheduler_dsn(): void {
		Functions\expect( 'wp_parse_url' )
			->once()
			->with( 'action_scheduler://exports', PHP_URL_SCHEME )
			->andReturn( 'action_scheduler' );

		self::assertTrue( ( new ActionSchedulerTransportFactory() )->supports( 'action_scheduler://exports', [] ) );
	}

	public function test_supports_rejects_other_dsn_schemes(): void {
		Functions\expect( 'wp_parse_url' )
			->once()
			->with( 'sync://default', PHP_URL_SCHEME )
			->andReturn( 'sync' );

		self::assertFalse( ( new ActionSchedulerTransportFactory() )->supports( 'sync://default', [] ) );
	}

	#[DataProvider( 'dsn_queue_name_provider' )]
	public function test_create_transport_uses_queue_name_from_dsn( string $dsn, mixed $host, mixed $path, string $expected_queue ): void {
		$serializer = $this->createMock( SerializerInterface::class );
		$envelope   = new Envelope( new \stdClass() );

		Functions\expect( 'wp_parse_url' )
			->once()
			->with( $dsn, PHP_URL_HOST )
			->andReturn( $host );
		Functions\expect( 'wp_parse_url' )
			->once()
			->with( $dsn, PHP_URL_PATH )
			->andReturn( $path );

		$serializer->expects( self::once() )
			->method( 'encode' )
			->with( $envelope )
			->willReturn(
				[
					'body'    => 'message',
					'headers' => [],
				]
			);

		Functions\expect( 'as_enqueue_async_action' )
			->once()
			->with(
				ActionSchedulerTransport::HOOK_DEFAULT,
				[
					'queue_name'    => $expected_queue,
					'envelope'      => [
						'body'    => 'message',
						'headers' => [],
					],
					'message_class' => \stdClass::class,
				],
				'storeaccountant'
			)
			->andReturn( 123 );
		Functions\expect( 'is_wp_error' )
			->once()
			->with( 123 )
			->andReturn( false );

		$transport = ( new ActionSchedulerTransportFactory() )->createTransport( $dsn, [], $serializer );

		self::assertInstanceOf( ActionSchedulerTransport::class, $transport );
		$transport->send( $envelope );
	}

	/**
	 * @return iterable<string,array{0:string,1:mixed,2:mixed,3:string}>
	 */
	public static function dsn_queue_name_provider(): iterable {
		yield 'host queue' => [ 'action_scheduler://exports', 'exports', '', 'exports' ];
		yield 'path queue' => [ 'action_scheduler://host/customer_exports', 'host', '/customer_exports', 'customer_exports' ];
		yield 'default queue' => [ 'action_scheduler://', '', '', 'exports' ];
	}
}
