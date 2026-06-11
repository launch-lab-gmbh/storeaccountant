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

namespace StoreAccountant\Queue\Loopback {
	function status_header( int $status ): void {
		\StoreAccountant\Tests\Unit\Queue\Loopback\QueueLoopbackEndpointFunctionMocks::status_header( $status );
	}
}

namespace StoreAccountant\Tests\Unit\Queue\Loopback {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use PHPUnit\Framework\TestCase;
	use RuntimeException;
	use StoreAccountant\Export\Download\DownloadPasswordManager;
	use StoreAccountant\Export\ExportPostType;
	use StoreAccountant\Export\ExportRepository;
	use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
	use StoreAccountant\Queue\Loopback\ActionSchedulerLoopbackRunner;
	use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
	use StoreAccountant\Queue\Loopback\QueueLoopbackEndpoint;
	use StoreAccountant\Queue\QueueTransportRegistry;
	use StoreAccountant\Security\ReversibleCrypto;

	/**
	 * Tests async queue loopback endpoint request handling.
	 */
	final class QueueLoopbackEndpointTest extends TestCase {
		/** @var array<string, mixed> */
		private array $post_values = [];

		/** @var array<int, int> */
		private array $statuses = [];

		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();

			if ( ! defined( 'StoreAccountant\\Queue\\Loopback\\MINUTE_IN_SECONDS' ) ) {
				define( 'StoreAccountant\\Queue\\Loopback\\MINUTE_IN_SECONDS', 60 );
			}

			$this->post_values                                     = [];
			$this->statuses                                        = [];
			QueueLoopbackEndpointFunctionMocks::$exception_message = '';
			$this->mock_wordpress_functions();
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_register_adds_privileged_and_public_admin_post_hooks(): void {
			$endpoint = $this->endpoint();

			Functions\expect( 'add_action' )
			->once()
			->with( 'admin_post_' . QueueLoopbackEndpoint::ACTION, [ $endpoint, 'handle' ] );
			Functions\expect( 'add_action' )
			->once()
			->with( 'admin_post_nopriv_' . QueueLoopbackEndpoint::ACTION, [ $endpoint, 'handle' ] );

			$endpoint->register();

			$this->addToAssertionCount( 1 );
		}

		public function test_handle_rejects_invalid_request_with_forbidden_status(): void {
			$this->post_values = [
				'export_id' => '42',
				'token'     => 'bad-token',
			];

			QueueLoopbackEndpointFunctionMocks::$exception_message = 'forbidden';

			$this->expectExceptionMessage( 'forbidden' );

			$this->endpoint()->handle();
		}

		public function test_handle_rejects_missing_token_with_forbidden_status(): void {
			$this->post_values = [
				'export_id' => '42',
				'token'     => '',
			];

			QueueLoopbackEndpointFunctionMocks::$exception_message = 'missing_token';

			$this->expectExceptionMessage( 'missing_token:403' );

			$this->endpoint()->handle();
		}

		private function endpoint(): QueueLoopbackEndpoint {
			return new QueueLoopbackEndpoint(
				new QueueLoopbackDispatcher( new QueueTransportRegistry() ),
				new ActionSchedulerLoopbackRunner(
					new ExportRepository(
						new ExportFilterSelectionSerializer(),
						new DownloadPasswordManager( new ReversibleCrypto() )
					)
				)
			);
		}

		private function mock_wordpress_functions(): void {
			Functions\when( 'filter_input' )->alias(
				fn ( int $type, string $name, int $filter = FILTER_DEFAULT ): mixed => INPUT_POST === $type ? ( $this->post_values[ $name ] ?? null ) : null
			);
			Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
				fn ( int $type, string $name, int $filter = FILTER_DEFAULT ): mixed => INPUT_POST === $type ? ( $this->post_values[ $name ] ?? null ) : null
			);
			Functions\when( 'sanitize_text_field' )->returnArg( 1 );
			Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
			Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
			Functions\when( 'get_post_type' )->alias( static fn ( int $post_id ): string => 42 === $post_id ? ExportPostType::POST_TYPE : 'post' );
			Functions\when( 'get_transient' )->alias(
				static fn ( string $key ): string|false => 'storeaccountant_loopback_token_42' === $key ? 'valid-token' : false
			);
			Functions\when( 'delete_transient' )->justReturn( true );
			Functions\when( 'StoreAccountant\\Queue\\Loopback\\get_post_type' )->alias( static fn ( int $post_id ): string => 42 === $post_id ? ExportPostType::POST_TYPE : 'post' );
			Functions\when( 'StoreAccountant\\Queue\\Loopback\\get_transient' )->alias(
				static fn ( string $key ): string|false => 'storeaccountant_loopback_token_42' === $key ? 'valid-token' : false
			);
			Functions\when( 'StoreAccountant\\Queue\\Loopback\\delete_transient' )->justReturn( true );
			Functions\when( '__' )->returnArg( 1 );
			Functions\when( 'do_action' )->justReturn( null );
		}
	}

	/**
	 * Namespaced function bridge for exit-based endpoint tests.
	 */
	final class QueueLoopbackEndpointFunctionMocks {
		public static string $exception_message = '';

		public static function status_header( int $status ): void {
			if ( '' !== self::$exception_message ) {
				throw new RuntimeException( self::$exception_message . ':' . (string) $status );
			}
		}
	}
}
