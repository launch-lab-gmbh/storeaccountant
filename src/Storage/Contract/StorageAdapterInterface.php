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

namespace StoreAccountant\Storage\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Storage\StorageFile;
use StoreAccountant\Storage\StorageFileConfiguration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a selectable storage adapter.
 */
interface StorageAdapterInterface extends RegistryItemInterface {
	/**
	 * Persists a generated export file and returns its storage reference.
	 *
	 * @param StorageFileConfiguration $configuration Storage file configuration.
	 *
	 * @return string|WP_Error
	 */
	public function persist( StorageFileConfiguration $configuration ): string|WP_Error;

	/**
	 * Deletes a persisted file.
	 *
	 * @param string $storage_path Storage reference returned by persist().
	 */
	public function delete_file( string $storage_path ): void;

	/**
	 * Deletes a persisted directory.
	 *
	 * @param string $storage_path Storage directory reference.
	 */
	public function delete_directory( string $storage_path ): void;

	/**
	 * Creates a directory.
	 *
	 * @param string $storage_path Storage directory reference.
	 */
	public function create_directory( string $storage_path ): void;

	/**
	 * Checks whether a directory exists.
	 *
	 * @param string $storage_path Storage directory reference.
	 */
	public function directory_exists( string $storage_path ): bool;

	/**
	 * Checks whether a persisted export file exists.
	 *
	 * @param string $storage_path Storage reference returned by persist().
	 */
	public function file_exists( string $storage_path ): bool;

	/**
	 * Sets visibility for a stored path.
	 *
	 * @param string $storage_path Storage reference.
	 * @param string $visibility   Visibility.
	 */
	public function set_visibility( string $storage_path, string $visibility ): void;

	/**
	 * Gets visibility for a stored path.
	 *
	 * @param string $storage_path Storage reference.
	 *
	 * @return string|WP_Error
	 */
	public function get_visibility( string $storage_path ): string|WP_Error;

	/**
	 * Gets the MIME type for a stored file.
	 *
	 * @param string $storage_path Storage reference.
	 *
	 * @return string|WP_Error
	 */
	public function get_mime_type( string $storage_path ): string|WP_Error;

	/**
	 * Gets the last modified timestamp for a stored file.
	 *
	 * @param string $storage_path Storage reference.
	 *
	 * @return int|WP_Error
	 */
	public function get_last_modified( string $storage_path ): int|WP_Error;

	/**
	 * Gets the file size in bytes for a stored file.
	 *
	 * @param string $storage_path Storage reference.
	 *
	 * @return int|WP_Error
	 */
	public function get_file_size( string $storage_path ): int|WP_Error;

	/**
	 * Gets a persisted export file for download or streaming.
	 *
	 * @param string $storage_path Storage reference returned by persist().
	 *
	 * @return StorageFile|WP_Error
	 */
	public function get_file( string $storage_path ): StorageFile|WP_Error;

	/**
	 * Ensures the storage adapter is ready for writes.
	 *
	 * @return true|WP_Error
	 */
	public function ensure(): true|WP_Error;
}
