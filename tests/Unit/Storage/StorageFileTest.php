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

namespace StoreAccountant\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\StorageFile;

/**
 * Tests stored file value objects.
 */
final class StorageFileTest extends TestCase {
	public function test_constructor_stores_file_data(): void {
		$stream = fopen( 'php://memory', 'rb+' );

		self::assertIsResource( $stream );

		$file = new StorageFile( $stream, 'orders.csv', 'text/csv' );

		self::assertSame( $stream, $file->stream );
		self::assertSame( 'orders.csv', $file->file_name );
		self::assertSame( 'text/csv', $file->mime_type );

		fclose( $stream );
	}
}
