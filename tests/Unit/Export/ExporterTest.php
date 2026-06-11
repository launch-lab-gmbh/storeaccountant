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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportRendererInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\Exporter;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use StoreAccountant\Storage\StorageFileConfiguration;
use WP_Error;

/**
 * Tests synchronous export orchestration.
 */
final class ExporterTest extends TestCase {
	/** @var array<string, mixed> */
	private array $meta = [];

	private string $artifact_path = '';

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		$this->meta          = [];
		$this->artifact_path = '';

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $value ) ?? '' )
		);
		Functions\when( 'wp_delete_file' )->alias(
			static function ( string $path ): void {
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		);
	}

	protected function tearDown(): void {
		if ( '' !== $this->artifact_path && is_file( $this->artifact_path ) ) {
			unlink( $this->artifact_path );
		}

		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_export_builds_renders_persists_and_updates_export_path(): void {
		$this->artifact_path = tempnam( sys_get_temp_dir(), 'storeaccountant-export-' );
		self::assertIsString( $this->artifact_path );
		file_put_contents( $this->artifact_path, 'csv' );

		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '55',
			ExportPostType::META_DOWNLOAD_TOKEN   => 'downloadtoken',
		];

		$adapter  = $this->adapter( [ [ 'id' => 1001 ] ] );
		$renderer = $this->renderer(
			static fn ( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact => new ExportArtifact(
				$GLOBALS['storeaccountant_exporter_artifact_path'],
				'csv',
				'text/csv'
			)
		);
		$storage  = $this->storage_adapter(
			function ( StorageFileConfiguration $configuration ): string {
				self::assertSame( $this->artifact_path, $configuration->source_path );
				self::assertSame( 'downloadtoken.csv', $configuration->file_name );
				self::assertSame( 'downloadtoken.csv', $configuration->internal_path );

				return 'exports/downloadtoken.zip';
			}
		);

		$GLOBALS['storeaccountant_exporter_artifact_path'] = $this->artifact_path;

		$this->mock_wordpress_state( $adapter, $renderer, $storage );

		self::assertTrue( $this->exporter()->export( 42 ) );
		self::assertSame( 'exports/downloadtoken.zip', $this->meta[ ExportPostType::META_PATH ] );
		self::assertFalse( is_file( $this->artifact_path ) );
	}

	public function test_export_propagates_storage_errors_and_deletes_temporary_artifact(): void {
		$this->artifact_path = tempnam( sys_get_temp_dir(), 'storeaccountant-export-' );
		self::assertIsString( $this->artifact_path );
		file_put_contents( $this->artifact_path, 'csv' );

		$this->meta = [
			ExportPostType::META_EXPORT_ADAPTER   => 'orders',
			ExportPostType::META_EXPORT_WRITER    => 'csv',
			ExportPostType::META_STORAGE_ENGINE   => 'local',
			ExportPostType::META_FILTERS          => '[]',
			ExportPostType::META_CONFIGURATION_ID => '0',
			ExportPostType::META_DOWNLOAD_TOKEN   => 'downloadtoken',
		];

		$error = new WP_Error( 'storage_failed', 'Storage failed' );

		$adapter  = $this->adapter( [ [ 'id' => 1001 ] ] );
		$renderer = $this->renderer( fn (): ExportArtifact => new ExportArtifact( $this->artifact_path, 'csv', 'text/csv' ) );
		$storage  = $this->storage_adapter( static fn (): WP_Error => $error );

		$this->mock_wordpress_state( $adapter, $renderer, $storage );

		self::assertSame( $error, $this->exporter()->export( 42 ) );
		self::assertArrayNotHasKey( ExportPostType::META_PATH, $this->meta );
		self::assertFalse( is_file( $this->artifact_path ) );
	}

	public function test_export_returns_error_for_missing_adapter(): void {
		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, array $items ): array => [] );
		Functions\when( 'get_post_meta' )->alias( static fn ( int $post_id, string $key ): string => 'missing' );

		$result = $this->exporter()->export( 42 );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'storeaccountant_export_adapter_unavailable', $result->get_error_code() );
	}

	private function mock_wordpress_state(
		ExportAdapterInterface $adapter,
		ExportRendererInterface $renderer,
		StorageAdapterInterface $storage
	): void {
		Functions\when( 'get_post_meta' )->alias(
			fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? ''
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->meta[ $key ] = $value;
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, array $items ) use ( $adapter, $renderer, $storage ): array {
				return match ( $hook ) {
					'storeaccountant_export_adapter' => [ $adapter ],
					'storeaccountant_export_renderer' => [ $renderer ],
					'storeaccountant_storage_adapter' => [ $storage ],
					'storeaccountant_export_field_provider',
					'storeaccountant_export_field_value_mutator',
					'storeaccountant_export_attachment_provider' => [],
					'storeaccountant_export_field_value_provider' => [
						new class() implements \StoreAccountant\Export\Contract\FieldValueProviderInterface {
							public function get_id(): string {
								return 'values';
							}

							public function supports( Field $field, \StoreAccountant\Export\ExportContext $context ): bool {
								return true;
							}

							public function get_values( mixed $item, FieldCollection $fields, \StoreAccountant\Export\ExportContext $context ): array {
								return [];
							}
						},
					],
					default => $items,
				};
			}
		);
	}

	/**
	 * @param array<int, mixed> $items Items.
	 */
	private function adapter( array $items ): ExportAdapterInterface {
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_id' )->willReturn( 'orders' );
		$adapter->method( 'get_items' )->willReturn( $items );
		$adapter->method( 'get_context' )->willReturn( new \StoreAccountant\Export\ExportContext( 'orders' ) );
		$adapter->method( 'get_additional_fields' )->willReturn( new FieldCollection( [ new Field( 'id', 'ID' ) ] ) );
		$adapter->method( 'get_additional_values' )
			->willReturnCallback( static fn ( mixed $item ): array => [ 'id' => new FieldValue( 'id', $item['id'] ) ] );
		$adapter->method( 'get_record_id' )->willReturnCallback( static fn ( mixed $item ): string => (string) $item['id'] );

		return $adapter;
	}

	/**
	 * @param callable(ExportDataset, ExportPayload): ExportArtifact|WP_Error $render Render callback.
	 */
	private function renderer( callable $render ): ExportRendererInterface {
		return new class( $render ) implements ExportRendererInterface {
			public function __construct(
				private readonly mixed $render
			) {}

			public function get_id(): string {
				return 'csv';
			}

			public function get_file_extension(): string {
				return 'csv';
			}

			public function get_mime_type(): string {
				return 'text/csv';
			}

			public function render( ExportDataset $dataset, ExportPayload $payload ): ExportArtifact|WP_Error {
				return ( $this->render )( $dataset, $payload );
			}
		};
	}

	/**
	 * @param callable(StorageFileConfiguration): string|WP_Error $persist Persist callback.
	 */
	private function storage_adapter( callable $persist ): StorageAdapterInterface {
		$storage = $this->createMock( StorageAdapterInterface::class );
		$storage->method( 'get_id' )->willReturn( 'local' );
		$storage->method( 'persist' )->willReturnCallback( $persist );

		return $storage;
	}

	private function exporter(): Exporter {
		return new Exporter(
			new StorageAdapterRegistry(),
			new ExportAdapterRegistry(),
			new ExportRendererRegistry(),
			new ExportDatasetBuilder(
				new FieldValueProviderRegistry(),
				new FieldValueMutatorRegistry(),
				new ExportFieldResolver( new FieldProviderRegistry(), new FieldMappingRepository() ),
				new \StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry()
			),
			new ExportRepository( new ExportFilterSelectionSerializer(), new DownloadPasswordManager( new ReversibleCrypto() ) ),
			new ExportStoragePathGenerator( new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' ) ),
			new ExportFilterSelectionSerializer()
		);
	}
}
