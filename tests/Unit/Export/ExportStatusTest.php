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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\ExportStatus;

/**
 * Tests export status helpers.
 */
final class ExportStatusTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	#[DataProvider( 'provide_valid_statuses' )]
	public function test_is_valid_accepts_known_statuses( string $status ): void {
		self::assertTrue( ExportStatus::is_valid( $status ) );
	}

	public function test_is_valid_rejects_unknown_status(): void {
		self::assertFalse( ExportStatus::is_valid( 'archived' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_valid_statuses(): array {
		return [
			'scheduled'  => [ ExportStatus::SCHEDULED ],
			'queued'     => [ ExportStatus::QUEUED ],
			'processing' => [ ExportStatus::PROCESSING ],
			'completed'  => [ ExportStatus::COMPLETED ],
			'failed'     => [ ExportStatus::FAILED ],
		];
	}

	#[DataProvider( 'provide_status_labels' )]
	public function test_get_label_returns_translated_label( string $status, string $label ): void {
		Functions\expect( '__' )
			->once()
			->with( $label, 'storeaccountant' )
			->andReturn( $label );

		self::assertSame( $label, ExportStatus::get_label( $status ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function provide_status_labels(): array {
		return [
			'scheduled'  => [ ExportStatus::SCHEDULED, 'Scheduled' ],
			'queued'     => [ ExportStatus::QUEUED, 'Queued' ],
			'processing' => [ ExportStatus::PROCESSING, 'Processing' ],
			'completed'  => [ ExportStatus::COMPLETED, 'Completed' ],
			'failed'     => [ ExportStatus::FAILED, 'Failed' ],
			'unknown'    => [ 'archived', 'Unknown' ],
		];
	}
}
