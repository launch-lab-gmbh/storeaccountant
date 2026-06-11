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

namespace StoreAccountant\Tests\Unit\Storage\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\Admin\StorageActivationNotice;

/**
 * Tests storage activation notice behavior.
 */
final class StorageActivationNoticeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_admin_notice_action(): void {
		$notice = new StorageActivationNotice();

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_notices', [ $notice, 'render' ] );

		$notice->register();

		self::assertTrue( true );
	}

	public function test_render_outputs_and_clears_non_empty_activation_message(): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( StorageActivationNotice::TRANSIENT_NAME )
			->andReturn( 'Storage could not be prepared.' );
		Functions\expect( 'delete_transient' )
			->once()
			->with( StorageActivationNotice::TRANSIENT_NAME );
		Functions\expect( 'esc_html' )
			->once()
			->with( 'Storage could not be prepared.' )
			->andReturn( 'Storage could not be prepared.' );

		ob_start();
		( new StorageActivationNotice() )->render();
		$output = (string) ob_get_clean();

		self::assertStringContainsString( 'notice notice-warning is-dismissible', $output );
		self::assertStringContainsString( 'Storage could not be prepared.', $output );
	}

	public function test_render_ignores_empty_or_non_string_messages(): void {
		Functions\expect( 'get_transient' )
			->times( 3 )
			->with( StorageActivationNotice::TRANSIENT_NAME )
			->andReturn( '', false, [ 'not-string' ] );
		Functions\expect( 'delete_transient' )->never();
		Functions\expect( 'esc_html' )->never();

		$notice = new StorageActivationNotice();

		ob_start();
		$notice->render();
		$notice->render();
		$notice->render();
		$output = (string) ob_get_clean();

		self::assertSame( '', $output );
	}
}
