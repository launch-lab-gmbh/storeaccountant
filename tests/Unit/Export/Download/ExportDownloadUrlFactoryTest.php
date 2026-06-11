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

namespace StoreAccountant\Tests\Unit\Export\Download;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\ExportPostType;

/**
 * Tests public export download URL generation.
 */
final class ExportDownloadUrlFactoryTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_url_uses_download_token_only(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 123, ExportPostType::META_DOWNLOAD_TOKEN, true )
			->andReturn( 'abc123token' );

		Functions\expect( 'home_url' )
			->once()
			->with( '/storeaccountant/export-download/abc123token/' )
			->andReturn( 'https://example.test/storeaccountant/export-download/abc123token/' );

		self::assertSame(
			'https://example.test/storeaccountant/export-download/abc123token/',
			( new ExportDownloadUrlFactory() )->get_url( 123 )
		);
	}
}
