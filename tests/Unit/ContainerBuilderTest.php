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

namespace StoreAccountant\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use League\Container\Container;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\ContainerBuilder;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\Exporter;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Queue\Messenger\QueueMessageBus;
use StoreAccountant\Queue\QueueTransportRegistry;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\RolePermissionRepository;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\StorageAdapterRegistry;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerTransportSerializerInterface;

/**
 * Tests the StoreAccountant service container configuration.
 */
final class ContainerBuilderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		Functions\when( 'apply_filters' )->alias( static fn ( string $hook, mixed $value ): mixed => $value );
		Functions\when( 'get_option' )->alias( static fn ( string $option, mixed $default = false ): mixed => $default );
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => $key );
		Functions\when( 'wp_upload_dir' )->alias(
			static fn (): array => [
				'basedir' => '/tmp/storeaccountant-uploads',
				'error'   => false,
			]
		);
		Functions\when( 'trailingslashit' )->alias( static fn ( string $path ): string => rtrim( $path, '/' ) . '/' );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => false );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_build_returns_shared_container_with_core_services(): void {
		$container = ( new ContainerBuilder() )->build();

		self::assertInstanceOf( Container::class, $container );
		self::assertSame( $container->get( PermissionChecker::class ), $container->get( PermissionChecker::class ) );
		self::assertInstanceOf( PermissionChecker::class, $container->get( PermissionChecker::class ) );
		self::assertInstanceOf( RolePermissionRepository::class, $container->get( RolePermissionRepository::class ) );
		self::assertInstanceOf( ExportRepository::class, $container->get( ExportRepository::class ) );
		self::assertInstanceOf( ExportAdapterRegistry::class, $container->get( ExportAdapterRegistry::class ) );
		self::assertInstanceOf( ExportRendererRegistry::class, $container->get( ExportRendererRegistry::class ) );
		self::assertInstanceOf( StorageAdapterRegistry::class, $container->get( StorageAdapterRegistry::class ) );
	}

	public function test_hook_services_are_resolvable_hook_registrars(): void {
		$container = ( new ContainerBuilder() )->build();

		foreach ( ContainerBuilder::HOOK_SERVICES as $service_id ) {
			self::assertInstanceOf( HookRegistrarInterface::class, $container->get( $service_id ), $service_id );
		}
	}

	public function test_registries_renderers_repositories_and_queue_services_are_wired(): void {
		$container = ( new ContainerBuilder() )->build();

		self::assertInstanceOf( FieldProviderRegistry::class, $container->get( FieldProviderRegistry::class ) );
		self::assertInstanceOf( FieldValueProviderRegistry::class, $container->get( FieldValueProviderRegistry::class ) );
		self::assertInstanceOf( ExportFieldResolver::class, $container->get( ExportFieldResolver::class ) );
		self::assertInstanceOf( ExportDatasetBuilder::class, $container->get( ExportDatasetBuilder::class ) );
		self::assertInstanceOf( Exporter::class, $container->get( Exporter::class ) );
		self::assertInstanceOf( AccountingHeaderBar::class, $container->get( AccountingHeaderBar::class ) );
		self::assertInstanceOf( QueueTransportRegistry::class, $container->get( QueueTransportRegistry::class ) );
		self::assertInstanceOf( MessengerTransportSerializerInterface::class, $container->get( MessengerTransportSerializerInterface::class ) );
		self::assertInstanceOf( QueueMessageBus::class, $container->get( MessageBusInterface::class ) );
	}

	public function test_local_storage_configuration_uses_wordpress_uploads(): void {
		$configuration = ( new ContainerBuilder() )->build()->get( LocalStorageConfiguration::class );

		self::assertInstanceOf( LocalStorageConfiguration::class, $configuration );
		self::assertSame( '/tmp/storeaccountant-uploads/storeaccountant', $configuration->root_path );
		self::assertSame( 'wp-content/uploads/storeaccountant', $configuration->display_root_path );
	}
}
