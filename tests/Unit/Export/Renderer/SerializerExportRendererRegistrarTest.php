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

namespace StoreAccountant\Tests\Unit\Export\Renderer;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;
use StoreAccountant\Export\Renderer\SerializerExportRendererRegistrar;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Tests serializer renderer registration.
 */
final class SerializerExportRendererRegistrarTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_adds_export_renderer_filter(): void {
		Functions\expect( 'add_filter' )
			->once()
			->with( 'storeaccountant_export_renderer', Mockery::type( 'callable' ), HookRegistrarInterface::DEFAULT_PRIORITY );

		$registrar = new SerializerExportRendererRegistrar(
			$this->createMock( ExportTemplateNormalizerInterface::class ),
			$this->createMock( SerializerInterface::class )
		);

		$registrar->register();

		self::assertTrue( true );
	}
}
