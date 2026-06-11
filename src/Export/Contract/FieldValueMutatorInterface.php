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
use StoreAccountant\Export\Field\FieldValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mutates resolved field values before writing an export.
 */
interface FieldValueMutatorInterface extends RegistryItemInterface {
	/**
	 * Checks whether this mutator supports the given field.
	 *
	 * @param Field         $field   Export field definition.
	 * @param ExportContext $context Runtime export context.
	 */
	public function supports( Field $field, ExportContext $context ): bool;

	/**
	 * Mutates one resolved field value.
	 *
	 * @param FieldValue           $value    Resolved field value.
	 * @param Field                $field    Export field definition.
	 * @param array<string, mixed> $settings Mutator settings from field mapping.
	 * @param ExportContext        $context  Runtime export context.
	 */
	public function mutate( FieldValue $value, Field $field, array $settings, ExportContext $context ): FieldValue;
}
