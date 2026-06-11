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

namespace StoreAccountant\Tests\Unit\Export\Filter;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Filter\ExportFilterSelection;

/**
 * Tests export filter selections.
 */
final class ExportFilterSelectionTest extends TestCase {
	public function test_constructor_stores_filter_id_and_settings(): void {
		$settings  = [
			'month' => '5',
			'year'  => 2026,
		];
		$selection = new ExportFilterSelection( 'order_date', $settings );

		self::assertSame( 'order_date', $selection->filter_id );
		self::assertSame( $settings, $selection->settings );
	}

	public function test_constructor_uses_empty_settings_by_default(): void {
		self::assertSame( [], ( new ExportFilterSelection( 'order_status' ) )->settings );
	}
}
