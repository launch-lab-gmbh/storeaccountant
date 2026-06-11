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
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\ExportTemplateNormalizerInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Renderer\SerializerExportRenderer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Tests serializer-backed export rendering.
 */
final class SerializerExportRendererTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_getters_return_constructor_metadata(): void {
		$renderer = $this->create_renderer();

		self::assertSame( 'json', $renderer->get_id() );
		self::assertSame( 'json', $renderer->get_file_extension() );
		self::assertSame( 'application/json', $renderer->get_mime_type() );
	}

	public function test_render_normalizes_serializes_and_writes_export_artifact(): void {
		global $wp_filesystem;

		$file_path     = tempnam( sys_get_temp_dir(), 'storeaccountant-json-' );
		$wp_filesystem = new class() {
			public array $writes = [];

			public function put_contents( string $path, string $contents ): bool {
				$this->writes[ $path ] = $contents;

				return false !== file_put_contents( $path, $contents );
			}
		};

		self::assertIsString( $file_path );

		$dataset = new ExportDataset( new FieldCollection(), [] );
		$payload = new ExportPayload( 42, 'orders' );
		$data    = [ [ 'Order Number' => '1001' ] ];

		$normalizer = $this->createMock( ExportTemplateNormalizerInterface::class );
		$normalizer->expects( self::once() )->method( 'normalize' )->with( $dataset, $payload )->willReturn( $data );

		$serializer = $this->createMock( SerializerInterface::class );
		$serializer->expects( self::once() )->method( 'serialize' )->with( $data, 'json' )->willReturn( '[{\"Order Number\":\"1001\"}]' );

		Functions\expect( 'wp_tempnam' )
			->once()
			->with( 'storeaccountant-export-42.json' )
			->andReturn( $file_path );
		Functions\when( 'WP_Filesystem' )->justReturn( true );

		$renderer = new SerializerExportRenderer( $normalizer, $serializer, 'json', 'json', 'json', 'application/json' );
		$artifact = $renderer->render( $dataset, $payload );

		self::assertSame( $file_path, $artifact->source_path );
		self::assertSame( 'json', $artifact->file_extension );
		self::assertSame( 'application/json', $artifact->mime_type );
		self::assertSame( '[{\"Order Number\":\"1001\"}]', file_get_contents( $file_path ) );
		self::assertSame( '[{\"Order Number\":\"1001\"}]', $wp_filesystem->writes[ $file_path ] );

		unlink( $file_path );
		$wp_filesystem = null;
	}

	private function create_renderer(): SerializerExportRenderer {
		return new SerializerExportRenderer(
			$this->createMock( ExportTemplateNormalizerInterface::class ),
			$this->createMock( SerializerInterface::class ),
			'json',
			'json',
			'json',
			'application/json'
		);
	}
}
