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

use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use function array_values;
use function in_array;
use function is_object;
use function is_scalar;
use function ksort;
use function method_exists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects exportable metadata fields from export context items.
 */
final readonly class MetaFieldCollector {
	/**
	 * Gets export fields for scalar metadata keys found in context items.
	 *
	 * @param ExportContext     $context            Runtime export context.
	 * @param string            $field_id_prefix    Field ID prefix.
	 * @param array<int,string> $reserved_meta_keys Metadata keys already represented by dedicated fields.
	 *
	 * @return array<string, Field>
	 */
	public function get_fields( ExportContext $context, string $field_id_prefix, array $reserved_meta_keys ): array {
		$fields = [];

		foreach ( $this->get_meta_keys( $context, $reserved_meta_keys ) as $meta_key ) {
			$field_id            = MetaField::get_field_id( $field_id_prefix, $meta_key );
			$fields[ $field_id ] = new Field(
				$field_id,
				$meta_key,
				options: [
					MetaField::OPTION_META_KEY => $meta_key,
				]
			);
		}

		return $fields;
	}

	/**
	 * Gets custom meta keys from context items.
	 *
	 * @param ExportContext     $context            Runtime export context.
	 * @param array<int,string> $reserved_meta_keys Metadata keys already represented by dedicated fields.
	 *
	 * @return array<int, string>
	 */
	private function get_meta_keys( ExportContext $context, array $reserved_meta_keys ): array {
		$meta_keys = [];

		foreach ( $context->items as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta_data' ) ) {
				continue;
			}

			foreach ( $item->get_meta_data() as $meta ) {
				$data     = is_object( $meta ) && method_exists( $meta, 'get_data' ) ? $meta->get_data() : [];
				$meta_key = isset( $data['key'] ) ? (string) $data['key'] : '';
				$value    = $data['value'] ?? null;

				if ( '' === $meta_key || isset( $meta_keys[ $meta_key ] ) || ! $this->is_exportable_meta( $meta_key, $value, $reserved_meta_keys ) ) {
					continue;
				}

				$meta_keys[ $meta_key ] = $meta_key;
			}
		}

		ksort( $meta_keys );

		return array_values( $meta_keys );
	}

	/**
	 * Checks whether a metadata key should be offered as a custom export field.
	 *
	 * @param string            $meta_key           Metadata key.
	 * @param mixed             $value              Example metadata value.
	 * @param array<int,string> $reserved_meta_keys Metadata keys already represented by dedicated fields.
	 */
	private function is_exportable_meta( string $meta_key, mixed $value, array $reserved_meta_keys ): bool {
		if ( in_array( $meta_key, $reserved_meta_keys, true ) ) {
			return false;
		}

		return is_scalar( $value ) || null === $value;
	}
}
