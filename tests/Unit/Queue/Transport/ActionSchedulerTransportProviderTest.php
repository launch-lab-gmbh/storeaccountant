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
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportProvider;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Tests Action Scheduler queue transport provider behavior.
 */
final class ActionSchedulerTransportProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_action_scheduler_transport_provider(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		( new ActionSchedulerTransportProvider() )->register();

		self::assertTrue( true );
	}

	public function test_metadata_describes_action_scheduler_transport(): void {
		$provider = new ActionSchedulerTransportProvider();

		self::assertSame( ActionSchedulerTransportProvider::PROVIDER_ID, $provider->get_id() );
		self::assertSame( 'Action Scheduler', $provider->get_label() );
		self::assertSame( 'action_scheduler://exports', $provider->get_dsn() );
		self::assertTrue( $provider->supports_manual_loopback() );
		self::assertStringContainsString( 'background', $provider->get_description() );
	}

	public function test_create_transport_returns_action_scheduler_transport(): void {
		self::assertInstanceOf(
			ActionSchedulerTransport::class,
			( new ActionSchedulerTransportProvider() )->create_transport( $this->createMock( SerializerInterface::class ) )
		);
	}
}
