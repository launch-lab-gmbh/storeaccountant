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

namespace StoreAccountant\Tests\Unit\Export\Attachment;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachment;

/**
 * Tests export attachments.
 */
final class ExportAttachmentTest extends TestCase {
	public function test_constructor_stores_attachment_data(): void {
		$stream = fopen( 'php://memory', 'rb+' );

		self::assertIsResource( $stream );

		$attachment = new ExportAttachment(
			$stream,
			'invoice.pdf',
			'application/pdf',
			'invoices/invoice.pdf'
		);

		self::assertSame( $stream, $attachment->stream );
		self::assertSame( 'invoice.pdf', $attachment->file_name );
		self::assertSame( 'application/pdf', $attachment->mime_type );
		self::assertSame( 'invoices/invoice.pdf', $attachment->internal_path );

		fclose( $stream );
	}
}
