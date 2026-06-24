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

namespace StoreAccountant\Export\Queue\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Message that appends one attachment chunk to a finalized export archive.
 */
final readonly class FinalizeExportAttachmentsMessage {
	public function __construct(
		public int $export_id,
		public ?string $renderer_id,
		public string $storage_path,
		public int $offset,
		public int $limit,
		public int $total_attachments
	) {}
}
