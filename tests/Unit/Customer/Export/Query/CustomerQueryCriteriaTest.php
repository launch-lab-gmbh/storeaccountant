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

namespace StoreAccountant\Tests\Unit\Customer\Export\Query;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Customer\Export\Query\CustomerQueryCriteria;
use StoreAccountant\Export\ExportPeriod;

/**
 * Tests customer query criteria defaults and value storage.
 */
final class CustomerQueryCriteriaTest extends TestCase {
	public function test_constructor_stores_values_unchanged(): void {
		$period    = new ExportPeriod( '2026-01-01 00:00:00', '2026-01-31 23:59:59' );
		$countries = [ 'DE', 'AT' ];
		$criteria  = new CustomerQueryCriteria(
			$period,
			CustomerQueryCriteria::DATE_FIELD_MODIFIED,
			$countries,
			CustomerQueryCriteria::COUNTRY_FIELD_SHIPPING,
			true,
			true
		);

		self::assertSame( $period, $criteria->period );
		self::assertSame( CustomerQueryCriteria::DATE_FIELD_MODIFIED, $criteria->date_field );
		self::assertSame( $countries, $criteria->countries );
		self::assertSame( CustomerQueryCriteria::COUNTRY_FIELD_SHIPPING, $criteria->country_field );
		self::assertTrue( $criteria->include_all_countries );
		self::assertTrue( $criteria->include_unassigned_country );
	}

	public function test_constructor_uses_stable_defaults(): void {
		$criteria = new CustomerQueryCriteria();

		self::assertNull( $criteria->period );
		self::assertSame( CustomerQueryCriteria::DATE_FIELD_CREATED, $criteria->date_field );
		self::assertSame( [], $criteria->countries );
		self::assertSame( CustomerQueryCriteria::COUNTRY_FIELD_BILLING, $criteria->country_field );
		self::assertFalse( $criteria->include_all_countries );
		self::assertFalse( $criteria->include_unassigned_country );
	}
}
