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

namespace StoreAccountant\Tests\Unit\Export\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\BooleanFieldType;
use StoreAccountant\Export\Field\Type\CustomFieldType;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Export\Field\Type\StringFieldType;

/**
 * Tests export field definitions.
 */
final class FieldTest extends TestCase {
	public function test_constructor_stores_field_data(): void {
		$type  = new NumberFieldType( NumberFieldType::FORMAT_DECIMAL );
		$field = new Field( 'total', 'Order Total', $type, [ 'currency' => 'EUR' ] );

		self::assertSame( 'total', $field->id );
		self::assertSame( 'Order Total', $field->label );
		self::assertSame( $type, $field->type );
		self::assertSame( [ 'currency' => 'EUR' ], $field->options );
	}

	/**
	 * @param class-string $expected_class Expected type class.
	 */
	#[DataProvider( 'provide_string_type_normalization_cases' )]
	public function test_constructor_normalizes_string_type_identifiers(
		string $type,
		string $expected_class,
		string $expected_id
	): void {
		$field = new Field( 'field', 'Field', $type );

		self::assertInstanceOf( $expected_class, $field->type );
		self::assertSame( $expected_id, $field->type->get_id() );
	}

	/**
	 * Provides field type normalization cases.
	 *
	 * @return array<string, array{string, class-string, string}>
	 */
	public static function provide_string_type_normalization_cases(): array {
		return [
			'string'         => [ StringFieldType::ID, StringFieldType::class, StringFieldType::ID ],
			'integer number' => [ NumberFieldType::FORMAT_INTEGER, NumberFieldType::class, NumberFieldType::ID ],
			'decimal number' => [ NumberFieldType::FORMAT_DECIMAL, NumberFieldType::class, NumberFieldType::ID ],
			'date'           => [ DateTimeFieldType::DATE_ID, DateTimeFieldType::class, DateTimeFieldType::DATE_ID ],
			'datetime'       => [ DateTimeFieldType::ID, DateTimeFieldType::class, DateTimeFieldType::ID ],
			'boolean'        => [ BooleanFieldType::ID, BooleanFieldType::class, BooleanFieldType::ID ],
			'custom'         => [ 'custom_vat_code', CustomFieldType::class, 'custom_vat_code' ],
		];
	}
}
