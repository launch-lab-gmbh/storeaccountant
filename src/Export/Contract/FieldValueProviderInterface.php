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
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves export field values for a dataset item.
 */
interface FieldValueProviderInterface extends RegistryItemInterface {
	/**
	 * Checks whether this provider supports the given export type and field.
	 *
	 * @param Field         $field   Export field definition.
	 * @param ExportContext $context Runtime export context.
	 */
	public function supports( Field $field, ExportContext $context ): bool;

	/**
	 * Gets values for one exported dataset item.
	 *
	 * @param mixed           $item    Exported source item.
	 * @param FieldCollection $fields  Selected fields.
	 * @param ExportContext   $context Export context.
	 *
	 * @return array<string, FieldValue>
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array;
}
