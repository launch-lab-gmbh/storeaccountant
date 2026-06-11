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
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Storage\StorageFileConfiguration;

/**
 * Tests storage file configurations.
 */
final class StorageFileConfigurationTest extends TestCase {
	public function test_constructor_stores_configuration_data(): void {
		$attachment = new ExportAttachment( 'stream', 'invoice.pdf', 'application/pdf', 'invoices/invoice.pdf' );

		$configuration = new StorageFileConfiguration(
			'exports/token.zip',
			'/tmp/orders.csv',
			'orders.csv',
			'orders.csv',
			[ $attachment ],
			'text/csv'
		);

		self::assertSame( 'exports/token.zip', $configuration->storage_path );
		self::assertSame( '/tmp/orders.csv', $configuration->source_path );
		self::assertSame( 'orders.csv', $configuration->file_name );
		self::assertSame( 'orders.csv', $configuration->internal_path );
		self::assertSame( [ $attachment ], $configuration->attachments );
		self::assertSame( 'text/csv', $configuration->mime_type );
	}

	public function test_constructor_uses_default_internal_path_attachments_and_mime_type(): void {
		$configuration = new StorageFileConfiguration( 'exports/token.csv', '/tmp/orders.csv', 'orders.csv' );

		self::assertNull( $configuration->internal_path );
		self::assertSame( [], $configuration->attachments );
		self::assertSame( 'application/octet-stream', $configuration->mime_type );
	}
}
