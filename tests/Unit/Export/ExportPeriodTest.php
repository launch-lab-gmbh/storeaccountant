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

namespace StoreAccountant\Tests\Unit\Export;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportPeriod;

/**
 * Tests export period value objects.
 */
final class ExportPeriodTest extends TestCase {
	public function test_constructor_stores_period_bounds(): void {
		$period = new ExportPeriod( '2026-05-01 00:00:00', '2026-05-31 23:59:59' );

		self::assertSame( '2026-05-01 00:00:00', $period->start_at );
		self::assertSame( '2026-05-31 23:59:59', $period->end_at );
	}
}
