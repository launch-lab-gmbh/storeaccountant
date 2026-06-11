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
 * Describes numeric field values.
 */
final readonly class NumberFieldType implements FieldTypeInterface {
	public const ID             = 'number';
	public const FORMAT_INTEGER = 'integer';
	public const FORMAT_DECIMAL = 'decimal';

	/**
	 * Initializes the number field type.
	 *
	 * @param string $format Numeric format.
	 */
	public function __construct(
		public string $format = self::FORMAT_DECIMAL
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * Checks whether this number field contains decimal values.
	 */
	public function is_decimal(): bool {
		return self::FORMAT_DECIMAL === $this->format;
	}
}
