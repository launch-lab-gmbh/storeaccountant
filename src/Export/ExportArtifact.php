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

namespace StoreAccountant\Export;

use StoreAccountant\Export\Attachment\ExportAttachment;
use function array_values;
use function iterator_to_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a rendered export artifact ready for storage.
 */
final readonly class ExportArtifact {
	/**
	 * Additional files to store alongside the rendered artifact.
	 *
	 * @var array<int, ExportAttachment>
	 */
	public array $attachments;

	/**
	 * Initializes the export artifact.
	 *
	 * @param string                     $source_path    Absolute path to the rendered export artifact.
	 * @param string                     $file_extension Generated file extension.
	 * @param string                     $mime_type      Generated file MIME type.
	 * @param iterable<ExportAttachment> $attachments Additional files to store alongside the rendered artifact.
	 */
	public function __construct(
		public string $source_path,
		public string $file_extension,
		public string $mime_type,
		iterable $attachments = []
	) {
		$this->attachments = array_values(
			is_array( $attachments ) ? $attachments : iterator_to_array( $attachments, false )
		);
	}
}
