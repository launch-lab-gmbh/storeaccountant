<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Uninstall;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Uninstall\Contract\UninstallCleanupTaskInterface;
use function add_filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers StoreAccountant's built-in uninstall cleanup tasks.
 */
final readonly class CoreUninstallCleanupTaskProvider implements HookRegistrarInterface {
	/**
	 * Initializes the provider.
	 *
	 * @param array<int, UninstallCleanupTaskInterface> $tasks Built-in cleanup tasks.
	 */
	public function __construct(
		private array $tasks
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter( 'storeaccountant_uninstall_cleanup_task', [ $this, 'register_tasks' ], HookRegistrarInterface::DEFAULT_PRIORITY );
	}

	/**
	 * Registers core uninstall cleanup tasks.
	 *
	 * @param array<string, UninstallCleanupTaskInterface> $tasks Registered tasks.
	 *
	 * @return array<string, UninstallCleanupTaskInterface>
	 */
	public function register_tasks( array $tasks ): array {
		foreach ( $this->tasks as $task ) {
			$tasks[ $task->get_id() ] = $task;
		}

		return $tasks;
	}
}
