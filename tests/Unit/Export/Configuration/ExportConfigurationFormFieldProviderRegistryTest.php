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

namespace StoreAccountant\Tests\Unit\Export\Configuration;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Contract\ExportConfigurationFormFieldProviderInterface;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests the export configuration form field provider registry.
 */
final class ExportConfigurationFormFieldProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_form_field_provider_hook_and_accepts_only_form_field_providers(): void {
		$provider = $this->createMock( ExportConfigurationFormFieldProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( 'invoice_attachments' );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_configuration_form_field_provider', [] )
			->andReturn(
				[
					new TestRegistryItem( 'not-a-form-field-provider' ),
					$provider,
				]
			);

		self::assertSame( [ 'invoice_attachments' => $provider ], ( new ExportConfigurationFormFieldProviderRegistry() )->get_all() );
	}
}
