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

use StoreAccountant\Export\Field\Field;
use function is_scalar;
use function md5;
use function str_starts_with;
use function substr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines shared metadata field conventions.
 */
final class MetaField {
	public const OPTION_META_KEY = 'meta_key';

	/**
	 * Gets a stable export field ID for a metadata key.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string $prefix   Field ID prefix.
	 * @param string $meta_key Metadata key.
	 */
	public static function get_field_id( string $prefix, string $meta_key ): string {
		return $prefix . substr( md5( $meta_key ), 0, 12 );
	}

	/**
	 * Checks whether a field represents metadata.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param Field       $field  Export field.
	 * @param string|null $prefix Optional required field ID prefix.
	 */
	public static function is_meta_field( Field $field, ?string $prefix = null ): bool {
		if ( null !== $prefix && ! str_starts_with( $field->id, $prefix ) ) {
			return false;
		}

		return isset( $field->options[ self::OPTION_META_KEY ] ) && is_scalar( $field->options[ self::OPTION_META_KEY ] );
	}
}
