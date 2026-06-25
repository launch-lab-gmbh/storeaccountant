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

namespace StoreAccountant\Export\Dataset;

use StoreAccountant\Export\Field\FieldValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds one record in an export dataset.
 */
final readonly class ExportRecord {
	/**
	 * Initializes the export record.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string|null            $id      Stable record identifier.
	 * @param array<int, FieldValue> $values  Record values.
	 * @param array<string, mixed>   $options Additional record options.
	 */
	public function __construct(
		public ?string $id,
		public array $values,
		public array $options = []
	) {}

	/**
	 * Gets a value by field identifier.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $field_id Field identifier.
	 */
	public function get_value( string $field_id ): mixed {
		foreach ( $this->values as $value ) {
			if ( $value->field_id === $field_id ) {
				return $value->value;
			}
		}

		return null;
	}
}
