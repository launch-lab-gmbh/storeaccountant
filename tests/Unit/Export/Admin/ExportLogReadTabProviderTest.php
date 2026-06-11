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
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Admin\ExportLogReadTabProvider;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests the export log read tab.
 */
final class ExportLogReadTabProviderTest extends TestCase {
	/** @var array<int, mixed> */
	private array $entries = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_wordpress_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_read_tab_provider(): void {
		$provider = $this->provider();

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_read_tab_provider', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertTrue( true );
	}

	public function test_supports_export_posts_when_log_permission_is_available(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports( $this->post( ExportPostType::POST_TYPE ) ) );
		self::assertFalse( $provider->supports( $this->post( 'post' ) ) );
		self::assertSame( [ ExportLogReadTabProvider::TAB_ID => 'Log' ], $provider->get_tabs( $this->post() ) );
	}

	public function test_render_sorts_newest_first_and_escapes_log_entries(): void {
		$this->entries = [
			[
				'time'    => '2026-05-01 10:00:00',
				'level'   => 'info',
				'message' => 'Started <export>',
				'context' => [ 'batch' => 1 ],
			],
			[
				'time'    => '2026-05-01 10:05:00',
				'level'   => 'error',
				'message' => 'Failed & retried',
				'context' => [ 'error' => '<boom>' ],
			],
		];

		$output = $this->render(
			static fn ( ExportLogReadTabProvider $provider ): null => $provider->render(
				ExportLogReadTabProvider::TAB_ID,
				new \WP_Post(
					[
						'ID'        => 77,
						'post_type' => ExportPostType::POST_TYPE,
					]
				)
			)
		);

		self::assertLessThan( strpos( $output, 'Started &lt;export&gt;' ), strpos( $output, 'Failed &amp; retried' ) );
		self::assertStringContainsString( 'storeaccountant-log-level--error', $output );
		self::assertStringContainsString( 'Error', $output );
		self::assertStringContainsString( '&lt;boom&gt;', $output );
	}

	public function test_render_empty_log_outputs_empty_state(): void {
		$output = $this->render(
			static fn ( ExportLogReadTabProvider $provider ): null => $provider->render(
				ExportLogReadTabProvider::TAB_ID,
				new \WP_Post(
					[
						'ID'        => 77,
						'post_type' => ExportPostType::POST_TYPE,
					]
				)
			)
		);

		self::assertStringContainsString( 'No export log entries are available.', $output );
	}

	/**
	 * @param callable(ExportLogReadTabProvider): void $callback Render callback.
	 */
	private function render( callable $callback ): string {
		ob_start();
		$callback( $this->provider() );

		return (string) ob_get_clean();
	}

	private function provider(): ExportLogReadTabProvider {
		return new ExportLogReadTabProvider( new PermissionChecker( new PermissionActionRegistry() ) );
	}

	private function post( string $post_type = ExportPostType::POST_TYPE ): \WP_Post {
		return new \WP_Post(
			[
				'ID'        => 77,
				'post_type' => $post_type,
			]
		);
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->alias( static fn ( string $text ): string => $text );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo htmlspecialchars( $text, ENT_QUOTES );
			}
		);
		Functions\when( 'esc_html' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn ( string $text ): string => htmlspecialchars( $text, ENT_QUOTES ) );
		Functions\when( 'sanitize_html_class' )->alias( static fn ( string $value ): string => preg_replace( '/[^A-Za-z0-9_-]/', '', $value ) ?? '' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value, int $flags = 0 ): string|false => json_encode( $value, $flags ) );
		Functions\when( 'get_post_meta' )->alias( fn (): array => $this->entries );
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_permission_action' === $hook
				? [ new PermissionAction( PermissionActionIds::EXPORT_VIEW_LOG, 'View export log', 'Exports', StoreAccountantCapabilities::VIEW_EXPORT_LOG ) ]
				: $value
		);
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array( $capability, [ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::VIEW_EXPORT_LOG ], true )
		);
	}
}
