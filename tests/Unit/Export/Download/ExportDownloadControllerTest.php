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

namespace StoreAccountant\Tests\Unit\Export\Download;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Download\ExportDownloadController;
use StoreAccountant\Export\Download\StorageFileStreamer;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests frontend export download request handling.
 */
final class ExportDownloadControllerTest extends TestCase {
	/** @var array<int, array<string, mixed>> */
	private array $meta = [];

	private string $token = '';

	private ?StorageAdapterInterface $adapter = null;

	/** @var array<int, int> */
	private array $statuses = [];

	private string $status_exception = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$_POST   = [];
		$_SERVER = [];

		$this->token            = 'downloadtoken';
		$this->status_exception = '';
		$this->meta             = [
			42 => [
				ExportPostType::META_DOWNLOAD_TOKEN => $this->token,
				ExportPostType::META_STATUS         => ExportStatus::COMPLETED,
				ExportPostType::META_DOWNLOAD_PASSWORD_HASH => password_hash( '>j(Hq^ENVD Xnz86v/<j/s', PASSWORD_DEFAULT ),
				ExportPostType::META_PATH           => 'exports/may.csv',
				ExportPostType::META_STORAGE_ENGINE => 'local',
			],
		];

		$this->mock_wordpress_functions();
	}

	protected function tearDown(): void {
		$_POST   = [];
		$_SERVER = [];

		Monkey\tearDown();
		Mockery::close();

		parent::tearDown();
	}

	public function test_register_adds_rewrite_query_var_and_request_hook(): void {
		$controller = $this->controller();

		Functions\expect( 'add_action' )->once()->with( 'init', [ $controller, 'register_rewrite_rule' ] );
		Functions\expect( 'add_filter' )->once()->with( 'query_vars', [ $controller, 'register_query_var' ] );
		Functions\expect( 'add_action' )->once()->with( 'template_redirect', [ $controller, 'handle_request' ] );

		$controller->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_rewrite_rule_and_query_var_are_registered(): void {
		Functions\expect( 'add_rewrite_rule' )
			->once()
			->with(
				'^storeaccountant/export-download/([^/]+)/?$',
				'index.php?storeaccountant_export_download=$matches[1]',
				'top'
			);

		$controller = $this->controller();
		$controller->register_rewrite_rule();

		self::assertSame( [ 'existing', 'storeaccountant_export_download' ], $controller->register_query_var( [ 'existing' ] ) );
	}

	public function test_handle_request_ignores_requests_without_token(): void {
		$this->token = '';

		Functions\expect( 'get_posts' )->never();

		$this->controller()->handle_request();

		self::assertSame( [], $this->statuses );
	}

	public function test_handle_request_renders_error_when_export_file_is_missing(): void {
		$this->adapter          = $this->storage_adapter( false );
		$this->status_exception = 'render_message';

		$this->expectExceptionMessage( 'render_message' );

		$this->handle_request_with_clean_output();
	}

	public function test_handle_request_rejects_wrong_password_after_nonce_check(): void {
		$this->adapter             = $this->storage_adapter( true );
		$this->status_exception    = 'password_form';
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = [
			'storeaccountant_export_download_nonce'    => 'nonce',
			'storeaccountant_export_download_password' => 'wrong',
		];

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'nonce', 'storeaccountant_export_download_42' )
			->andReturn( true );

		$this->expectExceptionMessage( 'password_form' );

		$this->handle_request_with_clean_output();
	}

	private function handle_request_with_clean_output(): void {
		ob_start();

		try {
			$this->controller()->handle_request();
		} finally {
			ob_end_clean();
		}
	}

	private function controller(): ExportDownloadController {
		return new ExportDownloadController(
			new StorageAdapterRegistry(),
			new DownloadPasswordManager( new ReversibleCrypto() ),
			new StorageFileStreamer()
		);
	}

	private function storage_adapter( bool $file_exists ): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'local' );
		$adapter->method( 'file_exists' )->with( 'exports/may.csv' )->willReturn( $file_exists );

		return $adapter;
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo $text;
			}
		);
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'filter_input' )->alias(
			fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => $this->filter_input_mock( $type, $name, $filter, $options )
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
			fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => $this->filter_input_mock( $type, $name, $filter, $options )
		);
		Functions\when( 'sanitize_file_name' )->alias(
			static fn ( string $file_name ): string => trim( str_replace( [ '"', '/', '\\' ], '', $file_name ) )
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'get_query_var' )->alias( fn ( string $name ): string => 'storeaccountant_export_download' === $name ? $this->token : '' );
		Functions\when( 'get_posts' )->alias(
			static fn ( array $args ): array => [ 42 ]
		);
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key, bool $single = false ): mixed => $this->meta[ $post_id ][ $key ] ?? ''
		);
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, mixed $value ): mixed => 'storeaccountant_storage_adapter' === $hook && null !== $this->adapter ? [ $this->adapter ] : $value
		);
		Functions\when( 'status_header' )->alias(
			function ( int $status ): void {
				$this->statuses[] = $status;

				if ( '' !== $this->status_exception ) {
					throw new RuntimeException( $this->status_exception );
				}
			}
		);
		Functions\when( 'nocache_headers' )->justReturn( null );
		Functions\when( 'language_attributes' )->justReturn( null );
		Functions\when( 'bloginfo' )->alias(
			static function ( string $show ): void {
				echo 'UTF-8';
			}
		);
		Functions\when( 'wp_head' )->justReturn( null );
		Functions\when( 'wp_footer' )->justReturn( null );
		Functions\when( 'wp_nonce_field' )->justReturn( null );
		Functions\when( 'get_the_title' )->justReturn( 'May Export' );
		Functions\when( 'home_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/' . ltrim( $path, '/' ) );
		Functions\when( 'ob_get_level' )->justReturn( 0 );
	}

	private function filter_input_mock( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed {
		$value = match ( $type ) {
			INPUT_GET => $_GET[ $name ] ?? null,
			INPUT_POST => $_POST[ $name ] ?? null,
			INPUT_SERVER => $_SERVER[ $name ] ?? null,
			default => null,
		};

		if ( FILTER_CALLBACK !== $filter || ! is_array( $options ) || ! is_callable( $options['options'] ?? null ) ) {
			return $value;
		}

		return $options['options']( $value );
	}
}
