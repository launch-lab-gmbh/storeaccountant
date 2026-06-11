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

namespace StoreAccountant\Tests\Unit\Security\Permission;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Security\Permission\PermissionAction;

/**
 * Tests permission action value objects.
 */
final class PermissionActionTest extends TestCase {
	public function test_constructor_and_getters_store_action_data(): void {
		$action = new PermissionAction(
			'export_create',
			'Create exports',
			'Exports',
			'storeaccountant_create_exports',
			'Allows creating accounting exports.'
		);

		self::assertSame( 'export_create', $action->get_id() );
		self::assertSame( 'Create exports', $action->get_label() );
		self::assertSame( 'Exports', $action->get_group() );
		self::assertSame( 'storeaccountant_create_exports', $action->get_capability() );
		self::assertSame( 'Allows creating accounting exports.', $action->get_description() );
	}

	public function test_description_defaults_to_empty_string(): void {
		$action = new PermissionAction(
			'export_read',
			'Read exports',
			'Exports',
			'storeaccountant_read_exports'
		);

		self::assertSame( '', $action->get_description() );
	}
}
