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

namespace StoreAccountant\Tests\Unit\Storage\Adapter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Storage\Adapter\LocalStorageReference;

/**
 * Tests local storage references.
 */
final class LocalStorageReferenceTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'sanitize_file_name' )->alias(
			static fn ( string $value ): string => str_replace( ' ', '-', $value )
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn ( string $value ): string => strtolower( str_replace( [ ' ', '_' ], '-', $value ) )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_from_storage_path_returns_empty_reference_for_empty_path(): void {
		$reference = LocalStorageReference::from_storage_path( '' );

		self::assertSame( '', $reference->archive_file );
		self::assertSame( '', $reference->path );
	}

	public function test_from_storage_path_sanitizes_relative_archive_path(): void {
		$reference = LocalStorageReference::from_storage_path( '../exports\\monthly report.zip' );

		self::assertSame( 'exports/monthly-report.zip', $reference->archive_file );
		self::assertSame( '', $reference->path );
	}

	public function test_for_archive_file_sanitizes_archive_and_internal_paths(): void {
		$reference = LocalStorageReference::for_archive_file(
			'exports/../monthly report.zip',
			'./invoices\\invoice 1001.pdf'
		);

		self::assertSame( 'exports/monthly-report.zip', $reference->archive_file );
		self::assertSame( 'invoices/invoice-1001.pdf', $reference->path );
	}
}
