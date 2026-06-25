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
use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;
use StoreAccountant\Uninstall\UninstallCleanupTaskRegistry;

/**
 * Tests uninstall cleanup task registry behavior.
 */
final class UninstallCleanupTaskRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_returns_registered_cleanup_tasks_by_id(): void {
		$task = new TestUninstallCleanupTask( 'settings' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_uninstall_cleanup_task', [] )
			->andReturn( [ $task ] );

		self::assertSame(
			[
				'settings' => $task,
			],
			( new UninstallCleanupTaskRegistry() )->get_all()
		);
	}

	public function test_get_all_ignores_invalid_cleanup_tasks(): void {
		$task = new TestUninstallCleanupTask( 'exports' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_uninstall_cleanup_task', [] )
			->andReturn(
				[
					new \stdClass(),
					new TestUninstallCleanupTask( '' ),
					$task,
				]
			);

		self::assertSame(
			[
				'exports' => $task,
			],
			( new UninstallCleanupTaskRegistry() )->get_all()
		);
	}
}

/**
 * Test cleanup task.
 */
final readonly class TestUninstallCleanupTask implements UninstallCleanupTaskInterface {
	public function __construct(
		private string $id
	) {}

	public function get_id(): string {
		return $this->id;
	}

	public function cleanup(): void {}
}
