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

namespace StoreAccountant\Export\Field;

use StoreAccountant\Export\Field\Type\BooleanFieldType;
use StoreAccountant\Export\Field\Type\CustomFieldType;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Export\Field\Type\StringFieldType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Describes one field in an export dataset.
 */
final readonly class Field {
	/**
	 * Field value type.
	 *
	 * @var FieldTypeInterface
	 */
	public FieldTypeInterface $type;

	/**
	 * Additional field options.
	 *
	 * @var array<string, mixed>
	 */
	public array $options;

	/**
	 * Initializes the export field.
	 *
	 * @param string                    $id      Stable field identifier.
	 * @param string                    $label   User-facing or file-facing field label.
	 * @param FieldTypeInterface|string $type    Field value type.
	 * @param array<string, mixed>      $options Additional field options.
	 */
	public function __construct(
		public string $id,
		public string $label,
		FieldTypeInterface|string $type = StringFieldType::ID,
		array $options = []
	) {
		$this->type    = $this->normalize_type( $type );
		$this->options = $options;
	}

	/**
	 * Normalizes legacy string type identifiers into field type objects.
	 *
	 * @param FieldTypeInterface|string $type Field type.
	 */
	private function normalize_type( FieldTypeInterface|string $type ): FieldTypeInterface {
		if ( $type instanceof FieldTypeInterface ) {
			return $type;
		}

		return match ( $type ) {
			StringFieldType::ID              => new StringFieldType(),
			NumberFieldType::FORMAT_INTEGER => new NumberFieldType( NumberFieldType::FORMAT_INTEGER ),
			NumberFieldType::FORMAT_DECIMAL => new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ),
			DateTimeFieldType::DATE_ID      => new DateTimeFieldType( true ),
			DateTimeFieldType::ID           => new DateTimeFieldType(),
			BooleanFieldType::ID            => new BooleanFieldType(),
			default                         => new CustomFieldType( $type ),
		};
	}
}
