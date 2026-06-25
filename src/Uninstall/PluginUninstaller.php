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

use function flush_rewrite_rules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs StoreAccountant database cleanup during plugin uninstall.
 */
final readonly class PluginUninstaller {
	/**
	 * Removes StoreAccountant database artifacts.
	 */
	public function uninstall(): void {
		( new CoreUninstallCleanupTaskProvider(
			[
				new PluginSettingsCleanupTask(),
				new ExportConfigurationsCleanupTask(),
				new ExportsCleanupTask(),
			]
		) )->register();

		foreach ( ( new UninstallCleanupTaskRegistry() )->get_all() as $task ) {
			$task->cleanup();
		}

		flush_rewrite_rules( false );
	}
}
