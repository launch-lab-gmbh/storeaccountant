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

namespace StoreAccountant\Tests\Unit\Uninstall;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Uninstall\ExportConfigurationsCleanupTask;
use StoreAccountant\Uninstall\ExportsCleanupTask;

/**
 * Tests database-only custom post type cleanup tasks.
 */
final class PostTypeDatabaseCleanupTaskTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	/**
	 * @return iterable<string, array{object, string}>
	 */
	public static function cleanup_tasks(): iterable {
		yield 'exports' => [ new ExportsCleanupTask(), ExportPostType::POST_TYPE ];
		yield 'export configurations' => [ new ExportConfigurationsCleanupTask(), ExportConfigurationPostType::POST_TYPE ];
	}

	#[DataProvider( 'cleanup_tasks' )]
	public function test_cleanup_deletes_database_records_for_post_type( object $task, string $post_type ): void {
		Functions\expect( 'get_posts' )
			->once()
			->with(
				[
					'fields'         => 'ids',
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'no_found_rows'  => true,
					'suppress_filters' => true,
				]
			)
			->andReturn( [ 11, 12 ] );
		Functions\expect( 'wp_delete_post' )
			->once()
			->with( 11, true );
		Functions\expect( 'wp_delete_post' )
			->once()
			->with( 12, true );

		$task->cleanup();

		self::assertTrue( true );
	}

	public function test_cleanup_does_not_run_delete_queries_without_records(): void {
		Functions\expect( 'get_posts' )
			->once()
			->andReturn( [] );
		Functions\expect( 'wp_delete_post' )
			->never();

		( new ExportsCleanupTask() )->cleanup();

		self::assertTrue( true );
	}
}
