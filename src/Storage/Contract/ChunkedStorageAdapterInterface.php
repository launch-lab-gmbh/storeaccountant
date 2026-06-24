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

namespace StoreAccountant\Storage\Contract;

use WP_Error;
use StoreAccountant\Storage\StorageFileConfiguration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supports persisting one export file in multiple queue-safe steps.
 */
interface ChunkedStorageAdapterInterface {
	/**
	 * Starts persisting the generated export file without additional attachments.
	 *
	 * @param StorageFileConfiguration $configuration Storage file configuration.
	 *
	 * @return string|WP_Error
	 */
	public function start_persist( StorageFileConfiguration $configuration ): string|WP_Error;

	/**
	 * Appends additional files to an already started export.
	 *
	 * @param string          $storage_path Storage path returned by start_persist().
	 * @param iterable<mixed> $attachments  Export attachments.
	 *
	 * @return true|WP_Error
	 */
	public function append_attachments( string $storage_path, iterable $attachments ): true|WP_Error;
}
