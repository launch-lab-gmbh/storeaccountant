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
use ReflectionClass;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests export configuration post type registration.
 */
final class ExportConfigurationPostTypeTest extends TestCase {
	/** @var array<string, mixed> */
	private array $registered_args = [];

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_register_post_type_uses_expected_capabilities_and_hidden_ui(): void {
		Functions\when( 'register_post_type' )->alias(
			function ( string $post_type, array $args ): void {
				$this->registered_args = [
					'post_type' => $post_type,
					'args'      => $args,
				];
			}
		);

		$this->post_type()->register_post_type();

		$args = $this->registered_args['args'];

		self::assertSame( ExportConfigurationPostType::POST_TYPE, $this->registered_args['post_type'] );
		self::assertFalse( $args['public'] );
		self::assertTrue( $args['show_ui'] );
		self::assertFalse( $args['show_in_menu'] );
		self::assertSame( [ 'title' ], $args['supports'] );
		self::assertTrue( $args['map_meta_cap'] );
		self::assertSame( 'do_not_allow', $args['capabilities']['create_posts'] );
		self::assertSame( StoreAccountantCapabilities::READ_CONFIGURATIONS, $args['capabilities']['edit_posts'] );
		self::assertSame( StoreAccountantCapabilities::EDIT_CONFIGURATION, $args['capabilities']['edit_published_posts'] );
		self::assertSame( StoreAccountantCapabilities::DELETE_CONFIGURATIONS, $args['capabilities']['delete_posts'] );
		self::assertSame( StoreAccountantCapabilities::CREATE_CONFIGURATIONS, $args['capabilities']['publish_posts'] );
		self::assertSame( 'Export Configurations', $args['labels']['name'] );
	}

	public function test_filter_columns_replaces_native_columns_with_configuration_metadata_columns(): void {
		$columns = $this->post_type()->filter_columns(
			[
				'cb'   => '<input />',
				'date' => 'Date',
			]
		);

		self::assertSame(
			[
				'cb',
				'title',
				ExportConfigurationPostType::META_EXPORT_ADAPTER,
				ExportConfigurationPostType::META_EXPORT_WRITER,
				ExportConfigurationPostType::META_STORAGE_ENGINE,
				'storeaccountant_created_at',
				'storeaccountant_created_by',
			],
			array_keys( $columns )
		);
		self::assertSame( '<input />', $columns['cb'] );
		self::assertSame( 'Storage Location', $columns[ ExportConfigurationPostType::META_STORAGE_ENGINE ] );
	}

	private function post_type(): ExportConfigurationPostType {
		return ( new ReflectionClass( ExportConfigurationPostType::class ) )->newInstanceWithoutConstructor();
	}
}
