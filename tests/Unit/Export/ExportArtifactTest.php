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

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\ExportArtifact;

/**
 * Tests rendered export artifact value objects.
 */
final class ExportArtifactTest extends TestCase {
	public function test_constructor_stores_artifact_metadata(): void {
		$artifact = new ExportArtifact( '/tmp/orders.csv', 'csv', 'text/csv' );

		self::assertSame( '/tmp/orders.csv', $artifact->source_path );
		self::assertSame( 'csv', $artifact->file_extension );
		self::assertSame( 'text/csv', $artifact->mime_type );
		self::assertSame( [], $artifact->attachments );
	}

	public function test_constructor_stores_array_attachments_without_reindexing(): void {
		$first  = new ExportAttachment( 'stream-a', 'invoice-a.pdf', 'application/pdf', 'invoices/invoice-a.pdf' );
		$second = new ExportAttachment( 'stream-b', 'invoice-b.pdf', 'application/pdf', 'invoices/invoice-b.pdf' );

		$artifact = new ExportArtifact(
			'/tmp/orders.csv',
			'csv',
			'text/csv',
			[
				'custom-key' => $first,
				7            => $second,
			]
		);

		self::assertSame( [ 'custom-key' => $first, 7 => $second ], $artifact->attachments );
	}

	public function test_constructor_keeps_traversable_attachments_lazy(): void {
		$attachment = new ExportAttachment( 'stream', 'invoice.pdf', 'application/pdf', 'invoices/invoice.pdf' );
		$opened     = 0;
		$generator  = static function () use ( $attachment, &$opened ): iterable {
			++$opened;

			yield $attachment;
		};

		$artifact = new ExportArtifact(
			'/tmp/orders.csv',
			'csv',
			'text/csv',
			$generator()
		);

		self::assertSame( 0, $opened );
		self::assertSame( [ $attachment ], iterator_to_array( $artifact->attachments, false ) );
		self::assertSame( 1, $opened );
	}
}
