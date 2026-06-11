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

namespace StoreAccountant\Tests\Unit\Export\Configuration\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\Admin\ExportConfigurationPageForm;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\Filter\Contract\ExportFilterFieldProviderInterface;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;
use StoreAccountant\Tax\Contract\OrderTaxFieldProviderInterface;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;

/**
 * Tests export configuration form rendering.
 */
final class ExportConfigurationPageFormTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	private bool $has_core_choices = true;

	private string $global_password = '';

	private string $configuration_password = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
		$this->mock_wordpress_functions();

		$global = ( new ReversibleCrypto() )->encrypt( 'global-secret' );
		$config = ( new ReversibleCrypto() )->encrypt( 'configuration-secret' );
		self::assertIsString( $global );
		self::assertIsString( $config );
		$this->global_password        = $global;
		$this->configuration_password = $config;
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_outputs_saved_values_provider_fields_and_password_state(): void {
		$this->meta = [
			ExportConfigurationPostType::META_FILTERS    => ( new ExportFilterSelectionSerializer() )->encode( [ new ExportFilterSelection( 'order_date', [ 'month' => '2026-05' ] ) ] ),
			ExportConfigurationPostType::META_EXPORT_ADAPTER => OrderExportAdapter::ADAPTER_ID,
			ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER => ExtendedOrderTaxFieldProvider::PROVIDER_ID,
			ExportConfigurationPostType::META_EXPORT_WRITER => 'csv',
			ExportConfigurationPostType::META_STORAGE_ENGINE => 'local',
			ExportConfigurationPostType::META_BATCH_SIZE => '25',
			ExportConfigurationPostType::META_ADDITIONAL_SETTINGS => '{"invoice":{"enabled":true}}',
			ExportConfigurationPostType::META_DOWNLOAD_PASSWORD => $this->configuration_password,
			ExportConfigurationPostType::META_DOWNLOAD_PASSWORD_HASH => 'hash',
		];

		$output = $this->render_form( $this->configuration() );

		self::assertStringContainsString( 'Configuration Title', $output );
		self::assertStringContainsString( 'May Configuration', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_configuration_id"', $output );
		self::assertStringContainsString( 'value="orders"', $output );
		self::assertStringContainsString( 'export_adapter_orders', $output );
		self::assertStringContainsString( 'Rendered configuration filter', $output );
		self::assertStringContainsString( 'value="csv"', $output );
		self::assertStringContainsString( 'value="local"', $output );
		self::assertStringContainsString( 'Tax Fields', $output );
		self::assertStringContainsString( 'Extended Tax Fields', $output );
		self::assertStringContainsString( 'Additional invoice fields', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_batch_size"', $output );
		self::assertStringContainsString( 'value="25"', $output );
		self::assertStringContainsString( 'Current Download Password', $output );
		self::assertStringContainsString( 'configuration-secret', $output );
	}

	public function test_render_defensively_handles_missing_core_choices(): void {
		$this->has_core_choices = false;

		$output = $this->render_form( $this->configuration() );

		self::assertStringContainsString( 'No export adapters are available.', $output );
		self::assertStringContainsString( 'No export formats are available.', $output );
		self::assertStringContainsString( 'No storage locations are enabled.', $output );
		self::assertStringContainsString( 'disabled="disabled"', $output );
	}

	public function test_render_create_mode_outputs_initial_title_and_submit_without_edit_sections(): void {
		$output = $this->render_form();

		self::assertStringContainsString( 'Save Configuration', $output );
		self::assertStringContainsString( 'name="storeaccountant_export_configuration_title"', $output );
		self::assertStringNotContainsString( 'storeaccountant_export_configuration_id', $output );
		self::assertStringNotContainsString( 'storeaccountant-export-writer', $output );
	}

	private function render_form( ?\WP_Post $configuration = null ): string {
		ob_start();
		( new ExportConfigurationPageForm(
			new StorageAdapterRegistry(),
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			new ExportConfigurationFormFieldProviderRegistry(),
			new ExportFilterFieldProviderRegistry(),
			new ExportFilterSelectionSerializer(),
			new OrderTaxFieldProviderField( new OrderTaxFieldProviderRegistry() ),
			new DownloadPasswordManager( new ReversibleCrypto() ),
			new PermissionChecker( new PermissionActionRegistry() )
		) )->render( $configuration );

		return (string) ob_get_clean();
	}

	private function configuration(): \WP_Post {
		return new \WP_Post(
			[
				'ID'         => 42,
				'post_type'  => ExportConfigurationPostType::POST_TYPE,
				'post_title' => 'May Configuration',
			]
		);
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
		Functions\when( 'add_query_arg' )->alias(
			static fn ( array $args, string $url ): string => $url . '?' . http_build_query( $args )
		);
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
		Functions\when( 'get_the_title' )->alias( static fn ( \WP_Post $post ): string => (string) $post->post_title );
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key, bool $single = false ): mixed => $this->meta[ $key ] ?? ''
		);
		Functions\when( 'get_option' )->alias(
			static fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'storeaccountant_download_password' => $this->global_password,
				'storeaccountant_download_password_hash' => 'global-hash',
				default => $default,
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => $key );
		Functions\when( 'selected' )->alias(
			static function ( string $selected, string $current ): void {
				if ( $selected === $current ) {
					echo 'selected="selected"';
				}
			}
		);
		Functions\when( 'disabled' )->alias(
			static function ( bool $disabled ): void {
				if ( $disabled ) {
					echo 'disabled="disabled"';
				}
			}
		);
		Functions\when( 'current_user_can' )->alias(
			static fn ( string $capability ): bool => in_array( $capability, [ StoreAccountantCapabilities::ACCESS_ADMIN, StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS ], true )
		);
		Functions\when( 'apply_filters' )->alias(
			fn ( string $hook, mixed $value ): mixed => match ( $hook ) {
				'storeaccountant_storage_adapter' => $this->has_core_choices ? [ $this->storage_adapter( 'local' ) ] : [],
				'storeaccountant_export_adapter' => $this->has_core_choices ? [ $this->export_adapter( OrderExportAdapter::ADAPTER_ID ) ] : [],
				'storeaccountant_export_renderer' => $this->has_core_choices ? [ $this->renderer( 'csv' ) ] : [],
				'storeaccountant_export_filter_field_provider' => $this->has_core_choices ? [ $this->filter_provider( 'order_date', OrderExportAdapter::ADAPTER_ID ) ] : [],
				'storeaccountant_export_configuration_form_field_provider' => $this->has_core_choices ? [ $this->configuration_field_provider( 'invoice' ) ] : [],
				'storeaccountant_export_order_tax_field_provider' => $this->has_core_choices ? [ $this->tax_provider( ExtendedOrderTaxFieldProvider::PROVIDER_ID ) ] : [],
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
				echo '<tr><td>Rendered configuration filter</td></tr>';
			}
		);

		return $provider;
	}

	private function configuration_field_provider( string $id ): ExportConfigurationFormFieldProviderInterface {
		$provider = $this->createMock( ExportConfigurationFormFieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'render_fields' )->willReturnCallback(
			static function (): void {
				echo '<tr><td>Additional invoice fields</td></tr>';
			}
		);

		return $provider;
	}

	private function tax_provider( string $id ): OrderTaxFieldProviderInterface {
		$provider = $this->createMock( OrderTaxFieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'get_label' )->willReturn( 'Extended Tax Fields' );

		return $provider;
	}
}
