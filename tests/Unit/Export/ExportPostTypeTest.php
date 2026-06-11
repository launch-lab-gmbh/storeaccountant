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
use ReflectionClass;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Security\Permission\StoreAccountantCapabilities;

/**
 * Tests saved export post type registration.
 */
final class ExportPostTypeTest extends TestCase {
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

		self::assertSame( ExportPostType::POST_TYPE, $this->registered_args['post_type'] );
		self::assertFalse( $args['public'] );
		self::assertTrue( $args['show_ui'] );
		self::assertFalse( $args['show_in_menu'] );
		self::assertSame( [ 'title' ], $args['supports'] );
		self::assertTrue( $args['map_meta_cap'] );
		self::assertSame( 'do_not_allow', $args['capabilities']['create_posts'] );
		self::assertSame( StoreAccountantCapabilities::READ_EXPORTS, $args['capabilities']['edit_posts'] );
		self::assertSame( StoreAccountantCapabilities::DELETE_EXPORTS, $args['capabilities']['delete_posts'] );
		self::assertSame( StoreAccountantCapabilities::CREATE_EXPORTS, $args['capabilities']['publish_posts'] );
		self::assertSame( 'Accounting Exports', $args['labels']['name'] );
	}

	public function test_filter_columns_replaces_native_columns_with_export_metadata_columns(): void {
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
				'storeaccountant_progress',
				ExportPostType::META_EXPORTED_AT,
				ExportPostType::META_EXPORT_ADAPTER,
				ExportPostType::META_EXPORT_WRITER,
				ExportPostType::META_TRIGGERED_BY,
				ExportPostType::META_CONFIGURATION_ID,
				ExportPostType::META_PATH,
			],
			array_keys( $columns )
		);
		self::assertSame( '<input />', $columns['cb'] );
		self::assertSame( 'Status / Download', $columns[ ExportPostType::META_PATH ] );
	}

	private function post_type(): ExportPostType {
		return ( new ReflectionClass( ExportPostType::class ) )->newInstanceWithoutConstructor();
	}
}
