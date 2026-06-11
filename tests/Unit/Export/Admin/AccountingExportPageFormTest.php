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
use StoreAccountant\Export\Admin\AccountingExportPageForm;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;

/**
 * Tests accounting quick export form rendering.
 */
final class AccountingExportPageFormTest extends TestCase {
	private bool $has_storage = true;

	private bool $has_adapters = true;

	private string $global_password = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_wordpress_functions();

		$encrypted = ( new ReversibleCrypto() )->encrypt( 'global-secret' );
		self::assertIsString( $encrypted );
		$this->global_password = $encrypted;
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_outputs_adapters_renderers_storage_batch_size_and_filter_fields(): void {
		$output = $this->render_form();

		self::assertStringContainsString( 'storeaccountant_start_export', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_title"', $output );
		self::assertStringContainsString( 'value="orders"', $output );
		self::assertStringContainsString( 'export_adapter_orders', $output );
		self::assertStringContainsString( 'value="csv"', $output );
		self::assertStringContainsString( 'exporter_csv', $output );
		self::assertStringContainsString( 'value="local"', $output );
		self::assertStringContainsString( 'storage_adapter_local', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_batch_size"', $output );
		self::assertStringContainsString( 'value="100"', $output );
		self::assertStringContainsString( 'data-storeaccountant-export-filter-group="1"', $output );
		self::assertStringContainsString( 'data-storeaccountant-export-type="orders"', $output );
		self::assertStringContainsString( 'Rendered order date filter', $output );
		self::assertStringContainsString( 'Current Download Password', $output );
		self::assertStringContainsString( 'global-secret', $output );
		self::assertStringContainsString( '<button type="submit" name="storeaccountant_start_quick_export">Start Quick Export</button>', $output );
	}

	public function test_render_outputs_empty_states_and_disabled_submit_when_core_choices_are_missing(): void {
		$this->has_storage  = false;
		$this->has_adapters = false;

		$output = $this->render_form();

		self::assertStringContainsString( 'No export adapters are available.', $output );
		self::assertStringContainsString( 'No export formats are available.', $output );
		self::assertStringContainsString( 'No storage locations are enabled.', $output );
		self::assertStringContainsString( 'disabled="disabled"', $output );
	}

	private function render_form(): string {
		ob_start();
		try {
			( new AccountingExportPageForm(
				new StorageAdapterRegistry(),
				new ExportAdapterRegistry(),
				new ExportRendererRegistry(),
				new ExportFilterFieldProviderRegistry(),
				new DownloadPasswordManager( new ReversibleCrypto() ),
				new PermissionChecker( new PermissionActionRegistry() )
			) )->render();

			return (string) ob_get_clean();
		} catch ( \Throwable $exception ) {
			ob_end_clean();
			throw $exception;
		}
	}

	private function mock_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text ): void {
				echo $text;
			}
		);
		Functions\when( 'admin_url' )->alias( static fn ( string $path = '' ): string => 'https://example.test/wp-admin/' . ltrim( $path, '/' ) );
		Functions\when( 'wp_nonce_field' )->alias(
			static function ( string $action, string $name ): void {
				echo '<input type="hidden" name="' . $name . '" value="nonce" />';
			}
		);
		Functions\when( 'submit_button' )->alias(
			static function ( string $text, string $type = 'primary', string $name = 'submit', bool $wrap = true, array $attributes = [] ): void {
				$disabled = isset( $attributes['disabled'] ) ? ' disabled="disabled"' : '';
				echo '<button type="submit" name="' . $name . '"' . $disabled . '>' . $text . '</button>';
			}
		);
		Functions\when( 'wp_kses' )->alias( static fn ( string $html, array $allowed_html ): string => $html );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'wp_salt' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'get_option' )->alias(
			fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'storeaccountant_download_password' => $this->global_password,
				'storeaccountant_download_password_hash' => 'secret-hash',
				default => $default,
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => $key );
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array( $capability, [ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ], true )
		);
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_storage_adapter' => $this->has_storage ? [ $this->storage_adapter( 'local' ) ] : [],
				'storeaccountant_export_adapter' => $this->has_adapters ? [ $this->export_adapter( OrderExportAdapter::ADAPTER_ID ) ] : [],
				'storeaccountant_export_renderer' => $this->has_adapters ? [ $this->renderer( 'csv' ) ] : [],
				'storeaccountant_export_filter_field_provider' => $this->has_adapters ? [ $this->filter_provider( 'order_date', OrderExportAdapter::ADAPTER_ID ) ] : [],
				'storeaccountant_permission_action' => [
					new PermissionAction( PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS, 'View passwords', 'Settings', StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ),
				],
				default => $value,
			}
		);
	}

	private function storage_adapter( string $id ): StorageAdapterInterface {
		$adapter = $this->createMock( StorageAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );

		return $adapter;
	}

	private function export_adapter( string $id ): ExportAdapterInterface {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( $id );

		return $adapter;
	}

	private function renderer( string $id ): ExportRendererInterface {
		$renderer = $this->createMock( ExportRendererInterface::class );
		$renderer->method( 'get_id' )->willReturn( $id );

		return $renderer;
	}

	private function filter_provider( string $id, string $export_type ): ExportFilterFieldProviderInterface {
		$provider = $this->createMock( ExportFilterFieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'supports' )->willReturnCallback( static fn ( string $type ): bool => $export_type === $type );
		$provider->method( 'get_default_selection' )->willReturn( new ExportFilterSelection( $id ) );
		$provider->method( 'render' )->willReturnCallback(
			static function (): void {
				echo '<tr><td>Rendered order date filter</td></tr>';
			}
		);

		return $provider;
	}
}
