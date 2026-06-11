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
use StoreAccountant\Export\Admin\ExportRawDataReadTabProvider;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;

/**
 * Tests the raw export data read tab.
 */
final class ExportRawDataReadTabProviderTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

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

	public function test_supports_export_posts_and_returns_raw_data_tab(): void {
		$provider = $this->provider();

		self::assertTrue( $provider->supports( $this->post( ExportPostType::POST_TYPE ) ) );
		self::assertFalse( $provider->supports( $this->post( 'post' ) ) );
		self::assertSame( [ ExportRawDataReadTabProvider::TAB_ID => 'Raw Data' ], $provider->get_tabs( $this->post() ) );
	}

	public function test_render_outputs_normalized_raw_data_and_decoded_filters(): void {
		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_CONFIGURATION_ID => '42',
			ExportPostType::META_FILTERS          => ( new ExportFilterSelectionSerializer() )->encode(
				[
					new ExportFilterSelection(
						'order_date',
						[
							'active' => true,
							'period' => [ 'month' => '5' ],
						]
					),
				]
			),
		];

		$output = $this->render();

		self::assertStringContainsString( 'export_title', $output );
		self::assertStringContainsString( 'May Export', $output );
		self::assertStringContainsString( 'export_adapter', $output );
		self::assertStringContainsString( 'orders', $output );
		self::assertStringContainsString( 'configuration_id', $output );
		self::assertStringContainsString( '42', $output );
		self::assertStringContainsString( 'filter.order_date', $output );
		self::assertStringContainsString( '&quot;active&quot;: true', $output );
		self::assertStringNotContainsString( 'download_password', strtolower( $output ) );
	}

	private function render(): string {
		ob_start();
		$this->provider()->render( ExportRawDataReadTabProvider::TAB_ID, $this->post() );

		return (string) ob_get_clean();
	}

	private function provider(): ExportRawDataReadTabProvider {
		return new ExportRawDataReadTabProvider( new ExportFilterSelectionSerializer() );
	}

	private function post( string $post_type = ExportPostType::POST_TYPE ): \WP_Post {
		return new \WP_Post(
			[
				'ID'         => 88,
				'post_type'  => $post_type,
				'post_title' => 'May Export',
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
		Functions\when( 'get_the_title' )->alias( static fn ( \WP_Post $post ): string => (string) $post->post_title );
		Functions\when( 'get_post_meta' )->alias(
			function ( int $post_id, string $key, bool $single = false ): mixed {
				return $this->meta[ $key ] ?? '';
			}
		);
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value, int $flags = 0 ): string|false => json_encode( $value, $flags ) );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\\-]/i', '', $key ) ?? '' ) );
	}
}
