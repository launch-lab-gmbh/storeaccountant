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

use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides additional files that should be stored with an export.
 */
interface ExportAttachmentProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether this provider supports the given export type.
	 *
	 * @param ExportContext $context Runtime export context.
	 */
	public function supports( ExportContext $context ): bool;

	/**
	 * Gets the base directory for this provider's attachments.
	 *
	 * @param ExportContext $context Runtime export context.
	 */
	public function get_directory( ExportContext $context ): string;

	/**
	 * Gets attachments for one exported source item.
	 *
	 * @param mixed         $item    Exported source item.
	 * @param ExportPayload $payload Export payload.
	 * @param ExportContext $context Export context.
	 *
	 * @return iterable<ExportAttachment>
	 */
	public function get_attachments( mixed $item, ExportPayload $payload, ExportContext $context ): iterable;
}
