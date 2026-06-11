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
use StoreAccountant\Export\ExportArtifact;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;

/**
 * Tests token-based export storage paths.
 */
final class ExportStoragePathGeneratorTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_local_storage_path_uses_download_token(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 7, ExportPostType::META_DOWNLOAD_TOKEN, true )
			->andReturn( '0123456789abcdef' );

		$generator = new ExportStoragePathGenerator(
			new LocalStorageConfiguration( '/tmp/storeaccountant', 'wp-content/uploads/storeaccountant' )
		);

		$configuration = $generator->generate(
			7,
			LocalStorageAdapter::ENGINE_ID,
			new ExportArtifact( '/tmp/export.csv', 'csv', 'text/csv' )
		);

		self::assertSame( 'exports/0123456789abcdef.zip', $configuration->storage_path );
		self::assertSame( '0123456789abcdef.csv', $configuration->file_name );
		self::assertSame( '0123456789abcdef.csv', $configuration->internal_path );
	}
}
