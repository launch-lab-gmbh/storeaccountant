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

namespace StoreAccountant\Export\Attachment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes an additional file that should be stored with an export.
 */
final readonly class ExportAttachment {
	/**
	 * Initializes the export attachment.
	 *
	 * @param resource $stream Attachment stream.
	 */
	public function __construct(
		public mixed $stream,
		public string $file_name,
		public string $mime_type,
		public string $internal_path
	) {}
}
