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

use StoreAccountant\Export\ExportPostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes saved export records from the database without deleting export files.
 */
final readonly class ExportsCleanupTask extends AbstractPostTypeDatabaseCleanupTask {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return 'exports';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_post_type(): string {
		return ExportPostType::POST_TYPE;
	}
}
