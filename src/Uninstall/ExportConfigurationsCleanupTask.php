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

use StoreAccountant\Export\Configuration\ExportConfigurationPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes saved export configuration records from the database.
 */
final readonly class ExportConfigurationsCleanupTask extends AbstractPostTypeDatabaseCleanupTask {
	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return 'export_configurations';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_post_type(): string {
		return ExportConfigurationPostType::POST_TYPE;
	}
}
