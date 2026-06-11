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
use StoreAccountant\Queue\Transport\SyncTransport;
use StoreAccountant\Queue\Transport\SyncTransportProvider;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Tests synchronous queue transport provider behavior.
 */
final class SyncTransportProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_sync_transport_provider_with_early_priority(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY - 1 );

		$this->provider()->register();

		self::assertTrue( true );
	}

	public function test_metadata_describes_synchronous_transport(): void {
		$provider = $this->provider();

		self::assertSame( SyncTransportProvider::PROVIDER_ID, $provider->get_id() );
		self::assertSame( 'Synchronous', $provider->get_label() );
		self::assertSame( 'sync://exports', $provider->get_dsn() );
		self::assertFalse( $provider->supports_manual_loopback() );
		self::assertStringContainsString( 'immediately', $provider->get_description() );
	}

	public function test_create_transport_returns_sync_transport(): void {
		self::assertInstanceOf(
			SyncTransport::class,
			$this->provider()->create_transport( $this->createMock( SerializerInterface::class ) )
		);
	}

	private function provider(): SyncTransportProvider {
		return new SyncTransportProvider( fn (): HandlersLocatorInterface => $this->createMock( HandlersLocatorInterface::class ) );
	}
}
