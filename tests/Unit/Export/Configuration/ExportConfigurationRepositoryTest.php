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
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Configuration\ExportConfigurationRepository;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use WP_Error;

/**
 * Tests export configuration persistence.
 */
final class ExportConfigurationRepositoryTest extends TestCase {
	/** @var array<string, mixed> */
	private array $inserted_post = [];

	/** @var array<string, mixed> */
	private array $updated_post = [];

	/** @var array<string, mixed> */
	private array $updated_meta = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'wp_json_encode' )->alias( static fn ( mixed $value ): string|false => json_encode( $value ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_create_writes_post_and_all_meta_fields(): void {
		Functions\when( 'wp_insert_post' )->alias(
			function ( array $post, bool $wp_error ): int {
				$this->inserted_post = $post;

				return 123;
			}
		);

		$filters = [ new ExportFilterSelection( 'order_date', [ 'month' => '5' ] ) ];
		$result  = $this->repository()->create( 'Monthly export', $filters, 'orders', 'csv', 'local', [ 'invoice' => true ], 1 );

		self::assertSame( 123, $result );
		self::assertSame( ExportConfigurationPostType::POST_TYPE, $this->inserted_post['post_type'] );
		self::assertSame( 'publish', $this->inserted_post['post_status'] );
		self::assertSame( 'Monthly export', $this->inserted_post['post_title'] );
		self::assertSame( 'orders', $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_EXPORT_ADAPTER ] );
		self::assertSame( 'csv', $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_EXPORT_WRITER ] );
		self::assertSame( 'local', $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_STORAGE_ENGINE ] );
		self::assertSame( (string) ExportPostType::MIN_BATCH_SIZE, $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_BATCH_SIZE ] );
		self::assertSame( json_encode( [ 'invoice' => true ] ), $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_ADDITIONAL_SETTINGS ] );
		self::assertStringContainsString( 'order_date', $this->inserted_post['meta_input'][ ExportConfigurationPostType::META_FILTERS ] );
	}

	public function test_update_updates_title_and_meta_fields(): void {
		Functions\when( 'wp_update_post' )->alias(
			function ( array $post, bool $wp_error ): int {
				$this->updated_post = $post;

				return (int) $post['ID'];
			}
		);
		Functions\when( 'update_post_meta' )->alias(
			function ( int $post_id, string $key, mixed $value ): void {
				$this->updated_meta[ $key ] = $value;
			}
		);

		$filters = [ new ExportFilterSelection( 'customer_country', [ 'countries' => [ 'DE' ] ] ) ];
		$result  = $this->repository()->update( 456, 'Updated export', $filters, 'customers', 'csv', 's3', [ 'foo' => 'bar' ], 250 );

		self::assertSame( 456, $result );
		self::assertSame( 456, $this->updated_post['ID'] );
		self::assertSame( ExportConfigurationPostType::POST_TYPE, $this->updated_post['post_type'] );
		self::assertSame( 'Updated export', $this->updated_post['post_title'] );
		self::assertSame( 'customers', $this->updated_meta[ ExportConfigurationPostType::META_EXPORT_ADAPTER ] );
		self::assertSame( 'csv', $this->updated_meta[ ExportConfigurationPostType::META_EXPORT_WRITER ] );
		self::assertSame( 's3', $this->updated_meta[ ExportConfigurationPostType::META_STORAGE_ENGINE ] );
		self::assertSame( '250', $this->updated_meta[ ExportConfigurationPostType::META_BATCH_SIZE ] );
		self::assertSame( json_encode( [ 'foo' => 'bar' ] ), $this->updated_meta[ ExportConfigurationPostType::META_ADDITIONAL_SETTINGS ] );
		self::assertStringContainsString( 'customer_country', $this->updated_meta[ ExportConfigurationPostType::META_FILTERS ] );
	}

	public function test_create_and_update_propagate_wordpress_errors(): void {
		$insert_error = new WP_Error( 'insert_failed' );
		$update_error = new WP_Error( 'update_failed' );

		Functions\when( 'wp_insert_post' )->alias( static fn (): WP_Error => $insert_error );
		self::assertSame( $insert_error, $this->repository()->create( 'Broken', [], 'orders', 'csv', 'local', [] ) );

		Functions\when( 'wp_update_post' )->alias( static fn (): WP_Error => $update_error );
		Functions\expect( 'update_post_meta' )->never();

		self::assertSame( $update_error, $this->repository()->update( 99, 'Broken', [], 'orders', 'csv', 'local', [] ) );
	}

	private function repository(): ExportConfigurationRepository {
		return new ExportConfigurationRepository( new ExportFilterSelectionSerializer() );
	}
}
