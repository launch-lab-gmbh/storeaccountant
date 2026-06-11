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
use ReflectionClass;
use StoreAccountant\Security\Permission\PermissionActionIds;

/**
 * Tests core permission action identifiers.
 */
final class PermissionActionIdsTest extends TestCase {
	public function test_constants_define_expected_action_ids(): void {
		self::assertSame(
			[
				'ACCESS_ADMIN'                     => 'admin.access',
				'MANAGE_SETTINGS'                  => 'settings.manage',
				'VIEW_DOWNLOAD_PASSWORDS'          => 'settings.view_download_passwords',
				'MANAGE_PERMISSIONS'               => 'permissions.manage',
				'EXPORT_LIST'                      => 'export.list',
				'EXPORT_VIEW'                      => 'export.view',
				'EXPORT_CREATE'                    => 'export.create',
				'EXPORT_DOWNLOAD'                  => 'export.download',
				'EXPORT_VIEW_LOG'                  => 'export.view_log',
				'EXPORT_DELETE'                    => 'export.delete',
				'CONFIGURATION_LIST'               => 'configuration.list',
				'CONFIGURATION_VIEW'               => 'configuration.view',
				'CONFIGURATION_CREATE'             => 'configuration.create',
				'CONFIGURATION_EDIT'               => 'configuration.edit',
				'CONFIGURATION_DELETE'             => 'configuration.delete',
				'CONFIGURATION_EDIT_FIELD_MAPPING' => 'configuration.edit_field_mapping',
			],
			( new ReflectionClass( PermissionActionIds::class ) )->getConstants()
		);
	}

	public function test_constructor_is_private(): void {
		$constructor = ( new ReflectionClass( PermissionActionIds::class ) )->getConstructor();

		self::assertNotNull( $constructor );
		self::assertTrue( $constructor->isPrivate() );
	}
}
