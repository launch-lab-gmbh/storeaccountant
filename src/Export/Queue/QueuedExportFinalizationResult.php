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

namespace StoreAccountant\Export\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes whether export finalization completed immediately or continues in attachment chunks.
 */
final readonly class QueuedExportFinalizationResult {
	public function __construct(
		public bool $complete,
		public string $storage_path,
		public int $total_attachments = 0,
		public int $attachment_batch_size = 0
	) {}

	/**
	 * Creates a completed finalization result.
	 *
	 * @param string $storage_path Final storage path.
	 */
	public static function complete( string $storage_path ): self {
		return new self( true, $storage_path );
	}

	/**
	 * Creates a pending attachment finalization result.
	 *
	 * @param string $storage_path           Final storage path.
	 * @param int    $total_attachments     Total attachment count.
	 * @param int    $attachment_batch_size Attachment chunk size.
	 */
	public static function pending( string $storage_path, int $total_attachments, int $attachment_batch_size ): self {
		return new self( false, $storage_path, $total_attachments, $attachment_batch_size );
	}
}
