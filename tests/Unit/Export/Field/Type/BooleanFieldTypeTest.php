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
use StoreAccountant\Export\Field\Type\BooleanFieldType;

/**
 * Tests boolean field types.
 */
final class BooleanFieldTypeTest extends TestCase {
	public function test_get_id_returns_stable_id(): void {
		self::assertSame( BooleanFieldType::ID, ( new BooleanFieldType() )->get_id() );
	}
}
