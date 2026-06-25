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

namespace StoreAccountant\Tests\Unit\Uninstall;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;
use StoreAccountant\Uninstall\CoreUninstallCleanupTaskProvider;

/**
 * Tests built-in uninstall cleanup task registration.
 */
final class CoreUninstallCleanupTaskProviderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_cleanup_task_filter(): void {
		$provider = new CoreUninstallCleanupTaskProvider( [] );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_uninstall_cleanup_task', [ $provider, 'register_tasks' ], HookRegistrarInterface::DEFAULT_PRIORITY );

		$provider->register();

		self::assertTrue( true );
	}

	public function test_register_tasks_adds_tasks_by_id(): void {
		$settings = new ProviderTestUninstallCleanupTask( 'settings' );
		$exports  = new ProviderTestUninstallCleanupTask( 'exports' );

		$provider = new CoreUninstallCleanupTaskProvider( [ $settings, $exports ] );

		self::assertSame(
			[
				'existing' => 'kept',
				'settings' => $settings,
				'exports'  => $exports,
			],
			$provider->register_tasks( [ 'existing' => 'kept' ] )
		);
	}
}

/**
 * Test cleanup task.
 */
final readonly class ProviderTestUninstallCleanupTask implements UninstallCleanupTaskInterface {
	public function __construct(
		private string $id
	) {}

	public function get_id(): string {
		return $this->id;
	}

	public function cleanup(): void {}
}
