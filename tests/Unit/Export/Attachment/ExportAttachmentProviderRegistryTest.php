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

namespace StoreAccountant\Tests\Unit\Export\Attachment;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Tests\Unit\Doubles\TestRegistryItem;

/**
 * Tests export attachment provider registry behavior.
 */
final class ExportAttachmentProviderRegistryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_all_uses_attachment_provider_hook_and_filters_by_type(): void {
		$provider = $this->provider( 'invoice', true );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_attachment_provider', [] )
			->andReturn( [ new TestRegistryItem( 'wrong-type' ), $provider ] );

		self::assertSame( [ 'invoice' => $provider ], ( new ExportAttachmentProviderRegistry() )->get_all() );
	}

	public function test_get_providers_returns_only_context_supported_providers(): void {
		$context     = new ExportContext( 'orders' );
		$supported   = $this->provider( 'invoice', true );
		$unsupported = $this->provider( 'packing-slip', false );

		Functions\expect( 'apply_filters' )
			->once()
			->with( 'storeaccountant_export_attachment_provider', [] )
			->andReturn( [ $supported, $unsupported ] );

		self::assertSame( [ 'invoice' => $supported ], ( new ExportAttachmentProviderRegistry() )->get_providers( $context ) );
	}

	private function provider( string $id, bool $supports ): ExportAttachmentProviderInterface {
		$provider = $this->createMock( ExportAttachmentProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'supports' )->willReturn( $supports );

		return $provider;
	}
}
