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

namespace StoreAccountant\Tests\Unit\Export\Field\Type;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\Type\NumberFieldType;

/**
 * Tests number field types.
 */
final class NumberFieldTypeTest extends TestCase {
	public function test_get_id_returns_stable_id(): void {
		self::assertSame( NumberFieldType::ID, ( new NumberFieldType() )->get_id() );
	}

	public function test_is_decimal_is_true_for_decimal_format(): void {
		$type = new NumberFieldType( NumberFieldType::FORMAT_DECIMAL );

		self::assertSame( NumberFieldType::FORMAT_DECIMAL, $type->format );
		self::assertTrue( $type->is_decimal() );
	}

	public function test_is_decimal_is_false_for_integer_format(): void {
		$type = new NumberFieldType( NumberFieldType::FORMAT_INTEGER );

		self::assertSame( NumberFieldType::FORMAT_INTEGER, $type->format );
		self::assertFalse( $type->is_decimal() );
	}
}
