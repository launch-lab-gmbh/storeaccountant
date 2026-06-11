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

namespace StoreAccountant\Tests\Unit\Security\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Security\Admin\SecuritySettingsForm;
use StoreAccountant\Security\Permission\PermissionAction;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;
use StoreAccountant\Security\ReversibleCrypto;

/**
 * Tests security settings form rendering.
 */
final class SecuritySettingsFormTest extends TestCase {
	/**
	 * @var array<string, mixed>
	 */
	private array $options = [];

	private bool $can_view_passwords = false;

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->alias( static fn ( string $text, string $domain = 'default' ): string => $text );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text, string $domain = 'default' ): void {
				echo $text;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'wp_salt' )->alias( static fn ( string $scheme = 'auth' ): string => 'unit-test-salt-' . $scheme );
		Functions\when( 'wp_generate_password' )->alias( static fn (): string => 'generated-secret' );
		Functions\when( 'wp_hash_password' )->alias( static fn ( string $password ): string => 'hash-' . $password );
		Functions\when( 'get_option' )->alias(
			fn ( string $option, mixed $default = false ): mixed => $this->options[ $option ] ?? $default
		);
		Functions\when( 'update_option' )->alias(
			function ( string $option, mixed $value, mixed $autoload = null ): bool {
				$this->options[ $option ] = $value;

				return true;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static fn ( string $hook, mixed $value ): mixed => 'storeaccountant_permission_action' === $hook
				? [
					new PermissionAction(
						PermissionActionIds::VIEW_DOWNLOAD_PASSWORDS,
						'View Passwords',
						'Settings',
						StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS
					),
				]
				: $value
		);
		Functions\when( 'current_user_can' )->alias(
			fn ( string $capability ): bool => match ( $capability ) {
				'manage_options' => false,
				StoreAccountantCapabilities::ACCESS_ADMIN => true,
				StoreAccountantCapabilities::VIEW_DOWNLOAD_PASSWORDS => $this->can_view_passwords,
				default => false,
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_render_fields_outputs_password_inputs_without_cleartext_leak_by_default(): void {
		$output = $this->render_form();

		self::assertStringContainsString( 'Global Download Password', $output );
		self::assertStringContainsString( 'type="password"', $output );
		self::assertStringContainsString( 'autocomplete="new-password"', $output );
		self::assertStringNotContainsString( 'generated-secret', $output );
		self::assertStringNotContainsString( 'Current Download Password', $output );
	}

	public function test_render_fields_reveals_current_password_when_permission_allows_it(): void {
		$this->manager()->save_global_password( 'current-secret' );
		$this->can_view_passwords = true;

		$output = $this->render_form();

		self::assertStringContainsString( 'Current Download Password', $output );
		self::assertStringContainsString( 'value="current-secret"', $output );
	}

	private function render_form(): string {
		ob_start();
		( new SecuritySettingsForm(
			$this->manager(),
			new PermissionChecker( new PermissionActionRegistry() )
		) )->render_fields();

		return (string) ob_get_clean();
	}

	private function manager(): DownloadPasswordManager {
		return new DownloadPasswordManager( new ReversibleCrypto() );
	}
}
