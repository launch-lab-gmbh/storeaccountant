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
use StoreAccountant\Export\Field\Type\DateTimeFieldType;

/**
 * Tests date and date-time field types.
 */
final class DateTimeFieldTypeTest extends TestCase {
	public function test_get_id_returns_datetime_id_by_default(): void {
		$type = new DateTimeFieldType();

		self::assertFalse( $type->date_only );
		self::assertSame( DateTimeFieldType::ID, $type->get_id() );
	}

	public function test_get_id_returns_date_id_for_date_only_type(): void {
		$type = new DateTimeFieldType( true );

		self::assertTrue( $type->date_only );
		self::assertSame( DateTimeFieldType::DATE_ID, $type->get_id() );
	}
}
