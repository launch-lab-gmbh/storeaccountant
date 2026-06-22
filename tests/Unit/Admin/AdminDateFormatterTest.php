<?php
/**
 * StoreAccountant
 * Export plugin for WooCommerce accounting workflows.
 *
 * @copyright   LaunchLab GmbH
 * @author-uri  https://launch-lab.de
 * @license     GPL-3.0-or-later
 */

declare(strict_types=1);

namespace StoreAccountant\Tests\Unit\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Admin\AdminDateFormatter;

/**
 * Tests admin date formatting.
 */
final class AdminDateFormatterTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_get_datetime_format_uses_twenty_four_hour_time_for_german_locale(): void {
		$this->mock_options( 'de_DE' );

		self::assertSame( 'F j, Y H:i', AdminDateFormatter::get_datetime_format() );
	}

	public function test_get_datetime_format_keeps_wordpress_time_format_for_english_locale(): void {
		$this->mock_options( 'en_US' );

		self::assertSame( 'F j, Y g:i a', AdminDateFormatter::get_datetime_format() );
	}

	public function test_format_mysql_datetime_uses_locale_aware_format(): void {
		$this->mock_options( 'de_DE' );
		Functions\when( 'wp_date' )->alias( static fn ( string $format, int $timestamp ): string => gmdate( $format, $timestamp ) );

		self::assertSame( 'June 22, 2026 08:50', AdminDateFormatter::format_mysql_datetime( '2026-06-22 08:50:00' ) );
		self::assertSame( '', AdminDateFormatter::format_mysql_datetime( 'not-a-date' ) );
	}

	private function mock_options( string $locale ): void {
		Functions\when( 'get_user_locale' )->alias( static fn (): string => $locale );
		Functions\when( 'get_option' )->alias(
			static fn ( string $option, mixed $default = false ): mixed => match ( $option ) {
				'date_format' => 'F j, Y',
				'time_format' => 'g:i a',
				default => $default,
			}
		);
	}
}
