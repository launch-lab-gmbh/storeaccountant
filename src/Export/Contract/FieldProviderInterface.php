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
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines export fields for a dataset.
 */
interface FieldProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether this provider supports the given export type.
	 *
	 * @param ExportContext $context Runtime export context.
	 */
	public function supports( ExportContext $context ): bool;

	/**
	 * Gets export fields.
	 *
	 * @param ExportContext $context Runtime export context.
	 *
	 * @return array<string, Field>
	 */
	public function get_fields( ExportContext $context ): array;
}
