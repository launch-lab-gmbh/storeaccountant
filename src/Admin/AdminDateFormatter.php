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

namespace StoreAccountant\Admin;

use function get_option;
use function get_user_locale;
use function sprintf;
use function str_starts_with;
use function strtotime;
use function wp_date;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats admin dates consistently with the active WordPress locale.
 */
final readonly class AdminDateFormatter {
	/**
	 * Gets the admin datetime format for the active user locale.
	 */
	public static function get_datetime_format(): string {
		return sprintf(
			'%1$s %2$s',
			(string) get_option( 'date_format' ),
			self::get_time_format()
		);
	}

	/**
	 * Formats a stored UTC MySQL datetime.
	 *
	 * @param string $datetime Date and time in MySQL format.
	 */
	public static function format_mysql_datetime( string $datetime ): string {
		$timestamp = strtotime( $datetime . ' UTC' );

		if ( false === $timestamp ) {
			return '';
		}

		return wp_date( self::get_datetime_format(), $timestamp );
	}

	/**
	 * Gets a locale-aware admin time format.
	 */
	private static function get_time_format(): string {
		$locale = get_user_locale();

		if ( 'de' === $locale || str_starts_with( $locale, 'de_' ) || str_starts_with( $locale, 'de-' ) ) {
			return 'H:i';
		}

		return (string) get_option( 'time_format' );
	}
}
