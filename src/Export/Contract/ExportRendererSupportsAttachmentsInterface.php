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

namespace StoreAccountant\Export\Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Marks export renderers that support persisting export attachments.
 */
interface ExportRendererSupportsAttachmentsInterface {}
