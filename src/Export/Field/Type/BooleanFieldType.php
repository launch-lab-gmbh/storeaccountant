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

namespace StoreAccountant\Export\Field\Type;

use StoreAccountant\Export\Field\FieldTypeInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes boolean field values.
 */
final readonly class BooleanFieldType implements FieldTypeInterface {
	public const ID = 'boolean';

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}
}
