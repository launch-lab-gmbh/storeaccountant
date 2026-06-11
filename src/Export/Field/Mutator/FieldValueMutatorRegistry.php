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

namespace StoreAccountant\Export\Field\Mutator;

use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides registered export field value mutators.
 */
final readonly class FieldValueMutatorRegistry extends Registry {
	/**
	 * {@inheritDoc}
	 */
	protected function get_hook_name(): string {
		return 'storeaccountant_export_field_value_mutator';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_type(): string {
		return FieldValueMutatorInterface::class;
	}
}
