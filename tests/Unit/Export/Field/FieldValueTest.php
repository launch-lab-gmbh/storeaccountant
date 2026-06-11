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

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\FieldValue;

/**
 * Tests export field values.
 */
final class FieldValueTest extends TestCase {
	public function test_constructor_stores_field_value_data(): void {
		$value = new FieldValue( 'total', '42.00', [ 'format' => 'decimal' ] );

		self::assertSame( 'total', $value->field_id );
		self::assertSame( '42.00', $value->value );
		self::assertSame( [ 'format' => 'decimal' ], $value->options );
	}
}
