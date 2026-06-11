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
 * Describes date and date-time field values.
 */
final readonly class DateTimeFieldType implements FieldTypeInterface {
	public const ID      = 'datetime';
	public const DATE_ID = 'date';

	/**
	 * Initializes the date-time field type.
	 *
	 * @param bool $date_only Whether the value is date-only.
	 */
	public function __construct(
		public bool $date_only = false
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return $this->date_only ? self::DATE_ID : self::ID;
	}
}
