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

namespace StoreAccountant\Export\Field\Meta;

use Stringable;
use function is_scalar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formats metadata values for export output.
 */
final readonly class MetaFieldValueFormatter {
	/**
	 * Formats a metadata value for export output.
	 *
	 * @param mixed $value Metadata value.
	 */
	public function format( mixed $value ): string {
		if ( null === $value || false === $value ) {
			return '';
		}

		if ( true === $value ) {
			return '1';
		}

		if ( is_scalar( $value ) || $value instanceof Stringable ) {
			return (string) $value;
		}

		$encoded = wp_json_encode( $value );

		return false === $encoded ? '' : $encoded;
	}
}
