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

namespace StoreAccountant\Storage;

use StoreAccountant\Export\Attachment\ExportAttachment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a generated file that should be persisted by a storage adapter.
 */
final readonly class StorageFileConfiguration {
	/**
	 * Initializes the storage file configuration.
	 *
	 * @param string                       $storage_path Relative storage reference or object key.
	 * @param string                       $source_path   Absolute local source path to read from.
	 * @param string                       $file_name     File name to expose at the storage destination.
	 * @param string|null                  $internal_path Optional path inside an archive-like storage target.
	 * @param iterable<ExportAttachment>   $attachments Additional files to store alongside the generated export.
	 * @param string                       $mime_type     MIME type of the generated export file.
	 */
	public function __construct(
		public string $storage_path,
		public string $source_path,
		public string $file_name,
		public ?string $internal_path = null,
		public iterable $attachments = [],
		public string $mime_type = 'application/octet-stream'
	) {}
}
