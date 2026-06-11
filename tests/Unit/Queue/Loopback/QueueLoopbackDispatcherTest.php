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

namespace {
	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}
}

namespace StoreAccountant\Tests\Unit\Queue\Loopback {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use Mockery;
	use PHPUnit\Framework\TestCase;
	use StoreAccountant\Export\Event\ExportEvents;
	use StoreAccountant\Export\ExportPostType;
	use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
	use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
	use StoreAccountant\Queue\Loopback\QueueLoopbackEndpoint;
	use StoreAccountant\Queue\QueueTransportRegistry;

	/**
	 * Tests manual export queue loopback dispatching.
	 */
	final class QueueLoopbackDispatcherTest extends TestCase {
		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_maybe_dispatch_for_manual_export_ignores_missing_or_non_loopback_transport(): void {
			$this->mock_active_transport( $this->provider( 'sync', false ) );

			Functions\expect( 'wp_generate_password' )->never();
			Functions\expect( 'set_transient' )->never();
			Functions\expect( 'wp_remote_post' )->never();
			Functions\expect( 'do_action' )->never();

			$this->dispatcher()->maybe_dispatch_for_manual_export( 123 );

			self::assertTrue( true );
		}

		public function test_maybe_dispatch_for_manual_export_creates_token_logs_and_dispatches_request(): void {
			$this->mock_active_transport( $this->provider( 'action_scheduler', true ) );

			Functions\expect( 'wp_generate_password' )
			->once()
			->with( 32, false, false )
			->andReturn( 'loopback-token' );
			Functions\expect( 'set_transient' )
			->once()
			->with( 'storeaccountant_loopback_token_123', 'loopback-token', 15 * MINUTE_IN_SECONDS );
			Functions\expect( 'do_action' )
			->once()
			->with(
				ExportEvents::LOG_ENTRY->value,
				123,
				'info',
				'Manual export loopback runner requested.',
				[
					'export_id'          => 123,
					'transport_provider' => 'action_scheduler',
				]
			);
			$this->expect_valid_loopback_request( 123, 'loopback-token' );

			$this->dispatcher()->maybe_dispatch_for_manual_export( 123 );

			self::assertTrue( true );
		}

		public function test_dispatch_ignores_invalid_export_id_or_post_type(): void {
			Functions\expect( 'get_post_type' )
			->once()
			->with( 123 )
			->andReturn( 'post' );
			Functions\expect( 'wp_remote_post' )->never();

			$dispatcher = $this->dispatcher();
			$dispatcher->dispatch( 0, 'token' );
			$dispatcher->dispatch( 123, 'token' );

			self::assertTrue( true );
		}

		public function test_dispatch_logs_wp_error_response(): void {
			$error = new class() {
				public function get_error_code(): string {
					return 'http_request_failed';
				}

				public function get_error_data(): array {
					return [ 'reason' => 'Request failed' ];
				}
			};

			Functions\expect( 'get_post_type' )
			->once()
			->with( 123 )
			->andReturn( ExportPostType::POST_TYPE );
			Functions\expect( 'admin_url' )
			->once()
			->with( 'admin-post.php' )
			->andReturn( 'https://example.test/wp-admin/admin-post.php' );
			Functions\expect( 'wp_remote_post' )
			->once()
			->with( 'https://example.test/wp-admin/admin-post.php', Mockery::type( 'array' ) )
			->andReturn( $error );
			Functions\expect( 'is_wp_error' )
			->once()
			->with( $error )
			->andReturn( true );
			Functions\expect( 'do_action' )
			->once()
			->with(
				ExportEvents::LOG_ENTRY->value,
				123,
				'error',
				'Loopback request could not be started. The queued export will continue when the queue runner runs.',
				Mockery::on(
					static fn ( array $context ): bool => 123 === $context['export_id']
						&& 'http_request_failed' === $context['wp_error_code']
				)
			);

			$this->dispatcher()->dispatch( 123, 'token' );

			self::assertTrue( true );
		}

		public function test_token_lifecycle_uses_export_specific_transient(): void {
			Functions\expect( 'get_transient' )
			->once()
			->with( 'storeaccountant_loopback_token_123' )
			->andReturn( 'stored-token' );
			Functions\expect( 'set_transient' )
			->once()
			->with( 'storeaccountant_loopback_token_123', 'stored-token', 15 * MINUTE_IN_SECONDS );
			Functions\expect( 'delete_transient' )
			->once()
			->with( 'storeaccountant_loopback_token_123' );

			$dispatcher = $this->dispatcher();

			self::assertTrue( $dispatcher->is_valid_token( 123, 'stored-token' ) );
			$dispatcher->refresh_token( 123, 'stored-token' );
			$dispatcher->delete_token( 123 );
		}

		private function dispatcher(): QueueLoopbackDispatcher {
			return new QueueLoopbackDispatcher( new QueueTransportRegistry() );
		}

		private function mock_active_transport( ?QueueTransportProviderInterface $provider ): void {
			Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( null === $provider ? [] : [ $provider ] );
			Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( null !== $provider ? $provider->get_id() : 'sync' );
			Functions\expect( 'sanitize_key' )
			->once()
			->andReturnUsing( static fn ( string $id ): string => $id );
		}

		private function provider( string $id, bool $supports_manual_loopback ): QueueTransportProviderInterface {
			$provider = $this->createMock( QueueTransportProviderInterface::class );
			$provider->method( 'get_id' )->willReturn( $id );
			$provider->method( 'supports_manual_loopback' )->willReturn( $supports_manual_loopback );

			return $provider;
		}

		private function expect_valid_loopback_request( int $export_id, string $token ): void {
			Functions\expect( 'get_post_type' )
			->once()
			->with( $export_id )
			->andReturn( ExportPostType::POST_TYPE );
			Functions\expect( 'admin_url' )
			->once()
			->with( 'admin-post.php' )
			->andReturn( 'https://example.test/wp-admin/admin-post.php' );
			Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://example.test/wp-admin/admin-post.php',
				[
					'blocking' => false,
					'timeout'  => 1,
					'body'     => [
						'action'    => QueueLoopbackEndpoint::ACTION,
						'export_id' => (string) $export_id,
						'token'     => $token,
					],
				]
			)
			->andReturn( [ 'response' => [ 'code' => 200 ] ] );
			Functions\expect( 'is_wp_error' )
			->once()
			->with( [ 'response' => [ 'code' => 200 ] ] )
			->andReturn( false );
		}
	}
}
