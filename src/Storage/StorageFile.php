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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes a stored file that can be streamed to a caller.
 */
final readonly class StorageFile {
	/**
	 * Initializes the stored file.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param resource $stream File stream.
	 */
	public function __construct(
		public mixed $stream,
		public string $file_name,
		public string $mime_type
	) {}
}
