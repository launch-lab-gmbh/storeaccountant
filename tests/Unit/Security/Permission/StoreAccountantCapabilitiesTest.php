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
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests StoreAccountant WordPress capability identifiers.
 */
final class StoreAccountantCapabilitiesTest extends TestCase {
	public function test_constants_define_expected_capabilities(): void {
		self::assertSame(
			[
				'ACCESS_ADMIN'            => 'storeaccountant_access_admin',
				'MANAGE_SETTINGS'         => 'storeaccountant_manage_settings',
				'VIEW_DOWNLOAD_PASSWORDS' => 'storeaccountant_view_download_passwords',
				'MANAGE_PERMISSIONS'      => 'storeaccountant_manage_permissions',
				'READ_EXPORTS'            => 'storeaccountant_read_exports',
				'CREATE_EXPORTS'          => 'storeaccountant_create_exports',
				'VIEW_EXPORT'             => 'storeaccountant_view_export',
				'DOWNLOAD_EXPORT'         => 'storeaccountant_download_export',
				'VIEW_EXPORT_LOG'         => 'storeaccountant_view_export_log',
				'DELETE_EXPORTS'          => 'storeaccountant_delete_exports',
				'READ_CONFIGURATIONS'     => 'storeaccountant_read_configurations',
				'CREATE_CONFIGURATIONS'   => 'storeaccountant_create_configurations',
				'VIEW_CONFIGURATION'      => 'storeaccountant_view_configuration',
				'EDIT_CONFIGURATION'      => 'storeaccountant_edit_configuration',
				'DELETE_CONFIGURATIONS'   => 'storeaccountant_delete_configurations',
				'EDIT_FIELD_MAPPING'      => 'storeaccountant_edit_field_mapping',
			],
			( new ReflectionClass( StoreAccountantCapabilities::class ) )->getConstants()
		);
	}

	public function test_constructor_is_private(): void {
		$constructor = ( new ReflectionClass( StoreAccountantCapabilities::class ) )->getConstructor();

		self::assertNotNull( $constructor );
		self::assertTrue( $constructor->isPrivate() );
	}
}
