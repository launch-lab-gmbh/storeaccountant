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

namespace StoreAccountant\Tests\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Invoice\InvoiceFileType;

/**
 * Tests invoice file types.
 */
final class InvoiceFileTypeTest extends TestCase {
	public function test_constructor_stores_file_type_data(): void {
		$type = new InvoiceFileType( 'invoice_pdf', 'Invoice PDF' );

		self::assertSame( 'invoice_pdf', $type->id );
		self::assertSame( 'Invoice PDF', $type->label );
	}
}
