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

namespace StoreAccountant\Tests\Unit\Export\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StoreAccountant\Export\Admin\ExportListPollingAjaxController;
use StoreAccountant\Export\Admin\ExportListPollingResponseFactory;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests admin export list polling AJAX controller behavior.
 */
final class ExportListPollingAjaxControllerTest extends TestCase {
	/** @var array<int, array<string, mixed>> */
	private array $meta = [];

	private bool $can_list_exports = true;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		if ( ! defined( 'StoreAccountant\\Export\\Admin\\MINUTE_IN_SECONDS' ) ) {
			define( 'StoreAccountant\\Export\\Admin\\MINUTE_IN_SECONDS', 60 );
		}
		$_POST = [];
		$this->mock_wordpress_functions();
	}

	protected function tearDown(): void {
		$_POST = [];

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_ajax_action(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_ajax_' . ExportListPollingAjaxController::ACTION, [ $this->controller(), 'handle' ] );

		$this->controller()->register();

		$this->addToAssertionCount( 1 );
	}

	public function test_handle_checks_nonce_and_capability(): void {
		$this->can_list_exports = false;

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( ExportListPollingAjaxController::NONCE_ACTION, 'nonce' );
		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				[ 'message' => 'You are not allowed to view accounting exports.' ],
				403
			)
			->andThrow( new RuntimeException( 'json_error' ) );

		$this->expectExceptionMessage( 'json_error' );

		$this->controller()->handle();
	}

	public function test_handle_ignores_invalid_missing_or_unauthorized_ids_and_returns_rows(): void {
		$_POST['export_ids'] = [ '0', '77', '88', '99' ];

		$this->meta[77] = [
			ExportPostType::META_STATUS => ExportStatus::PROCESSING,
		];

		Functions\expect( 'check_ajax_referer' )
			->twice()
			->with( ExportListPollingAjaxController::NONCE_ACTION, 'nonce' );
		Functions\expect( 'wp_send_json_success' )
			->once()
			->andReturnUsing(
				static function ( array $payload ): void {
					TestCase::assertCount( 1, $payload['exports'] );
					TestCase::assertSame( 77, $payload['exports'][0]['id'] );
					TestCase::assertSame( ExportStatus::PROCESSING, $payload['exports'][0]['status'] );
					TestCase::assertTrue( $payload['exports'][0]['pollable'] );

					throw new RuntimeException( 'json_success' );
				}
			);

		$this->expectExceptionMessage( 'json_success' );

		$this->controller()->handle();
	}

	private function controller(): ExportListPollingAjaxController {
		return new ExportListPollingAjaxController(
			new ExportListPollingResponseFactory(
				new StorageAdapterRegistry(),
				new ExportDownloadUrlFactory(),
				new PermissionChecker( new PermissionActionRegistry() )
			),
			new PermissionChecker( new PermissionActionRegistry() )
		);
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'StoreAccountant\\Contract\\WordPress\\filter_input' )->alias(
			static fn ( int $type, string $name, int $filter = FILTER_DEFAULT, mixed $options = null ): mixed => INPUT_POST === $type ? ( $_POST[ $name ] ?? null ) : ( $_GET[ $name ] ?? null )
		);
		Functions\when( 'get_post_type' )->alias(
			static fn ( int $post_id ): string => match ( $post_id ) {
				77, 99 => ExportPostType::POST_TYPE,
				default => 'post',
			}
		);
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key, bool $single = false ): mixed => $this->meta[ $post_id ][ $key ] ?? ''
		);
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_permission_action' === $hook
				? [
					new PermissionAction( PermissionActionIds::EXPORT_LIST, 'List exports', 'Exports', StoreAccountantCapabilities::READ_EXPORTS ),
					new PermissionAction( PermissionActionIds::EXPORT_VIEW, 'View export', 'Exports', StoreAccountantCapabilities::VIEW_EXPORT ),
					new PermissionAction( PermissionActionIds::EXPORT_DOWNLOAD, 'Download export', 'Exports', StoreAccountantCapabilities::DOWNLOAD_EXPORT ),
				]
				: $value
		);
		Functions\when( 'current_user_can' )->alias(
			fn ( string $capability, mixed ...$args ): bool => match ( $capability ) {
				'manage_options' => false,
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::READ_EXPORTS => $this->can_list_exports,
				StoreAccountantCapabilities::VIEW_EXPORT => (int) ( $args[0] ?? 0 ) !== 99,
				StoreAccountantCapabilities::DOWNLOAD_EXPORT => true,
				default => false,
			}
		);
	}
}
