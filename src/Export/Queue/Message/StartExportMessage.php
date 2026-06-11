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

namespace StoreAccountant\Export\Queue\Message;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Message that starts generation for one saved export record.
 */
final readonly class StartExportMessage {
	/**
	 * Initializes the message.
	 *
	 * @param int         $export_id   Export post ID.
	 * @param string|null $renderer_id Optional renderer override.
	 */
	public function __construct(
		public int $export_id,
		public ?string $renderer_id = null
	) {}
}
