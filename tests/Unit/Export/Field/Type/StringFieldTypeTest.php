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
use StoreAccountant\Export\Field\Type\StringFieldType;

/**
 * Tests string field types.
 */
final class StringFieldTypeTest extends TestCase {
	public function test_get_id_returns_stable_id(): void {
		self::assertSame( StringFieldType::ID, ( new StringFieldType() )->get_id() );
	}
}
