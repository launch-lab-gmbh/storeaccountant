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

namespace StoreAccountant\Export\Field\Mapping;

use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\Meta\MetaField;
use function array_filter;
use function array_merge;
use function array_values;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function trim;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists and applies configured field mappings.
 */
final readonly class FieldMappingRepository {
	/**
	 * Gets mapped fields for an export configuration.
	 *
	 * @param int             $configuration_id Export configuration post ID.
	 * @param FieldCollection $available_fields Available field definitions.
	 */
	public function get_mapped_fields( int $configuration_id, FieldCollection $available_fields ): FieldCollection {
		$mapping = $configuration_id > 0 ? $this->get_mapping( $configuration_id ) : [];

		if ( [] === $mapping ) {
			return $available_fields;
		}

		$available = $available_fields->all();
		$fields    = [];

		foreach ( $mapping as $item ) {
			$field_id = isset( $item['field_id'] ) && is_string( $item['field_id'] ) ? $item['field_id'] : '';

			if ( '' === $field_id || ! isset( $available[ $field_id ] ) || ! $this->is_enabled( $item ) ) {
				continue;
			}

			$field               = $available[ $field_id ];
			$fields[ $field_id ] = new Field(
				$field->id,
				$this->get_label( $item, $field ),
				$field->type,
				$this->get_options( $item, $field )
			);
		}

		foreach ( $available as $field_id => $field ) {
			if ( isset( $fields[ $field_id ] ) || $this->has_mapping_item( $mapping, $field_id ) ) {
				continue;
			}

			$fields[ $field_id ] = $field;
		}

		return new FieldCollection( $this->move_meta_fields_to_end( $fields ) );
	}

	/**
	 * Gets mapping items ready for form rendering.
	 *
	 * @param int             $configuration_id Export configuration post ID.
	 * @param FieldCollection $available_fields Available field definitions.
	 *
	 * @return array<int, array{field_id: string, enabled: bool, label: string, options: array<string, mixed>}>
	 */
	public function get_form_items( int $configuration_id, FieldCollection $available_fields ): array {
		$stored    = $this->get_mapping( $configuration_id );
		$available = $available_fields->all();
		$items     = [];
		$used      = [];

		foreach ( $stored as $item ) {
			$field_id = isset( $item['field_id'] ) && is_string( $item['field_id'] ) ? $item['field_id'] : '';

			if ( '' === $field_id || ! isset( $available[ $field_id ] ) || isset( $used[ $field_id ] ) ) {
				continue;
			}

			$field             = $available[ $field_id ];
			$items[]           = [
				'field_id' => $field_id,
				'enabled'  => $this->is_enabled( $item ),
				'label'    => $this->get_label( $item, $field ),
				'options'  => $this->get_options( $item, $field ),
			];
			$used[ $field_id ] = true;
		}

		foreach ( $available as $field_id => $field ) {
			if ( isset( $used[ $field_id ] ) ) {
				continue;
			}

			$items[] = [
				'field_id' => $field_id,
				'enabled'  => true,
				'label'    => $field->label,
				'options'  => [],
			];
		}

		return $this->move_meta_items_to_end( $items, $available );
	}

	/**
	 * Sanitizes submitted mapping rows.
	 *
	 * @param array<string, mixed> $request          Request data.
	 * @param FieldCollection      $available_fields Available field definitions.
	 *
	 * @return array<int, array{field_id: string, enabled: bool, label: string, options: array<string, mixed>}>
	 */
	public function sanitize_from_request( array $request, FieldCollection $available_fields ): array {
		$raw_mapping = $request['storeaccountant_field_mapping'] ?? [];
		$raw_mapping = is_array( $raw_mapping ) ? wp_unslash( $raw_mapping ) : [];
		$field_order = $this->get_field_order_from_request( $request, $available_fields );
		$mapping     = [];

		foreach ( $field_order as $field_id ) {
			$field   = $available_fields->all()[ $field_id ];
			$row     = isset( $raw_mapping[ $field_id ] ) && is_array( $raw_mapping[ $field_id ] ) ? $raw_mapping[ $field_id ] : [];
			$label   = isset( $row['label'] ) && is_scalar( $row['label'] ) ? trim( sanitize_text_field( (string) $row['label'] ) ) : '';
			$options = isset( $row['options'] ) && is_array( $row['options'] ) ? $this->sanitize_options( $row['options'] ) : [];

			$mapping[] = [
				'field_id' => $field_id,
				'enabled'  => isset( $row['enabled'] ) && '1' === (string) $row['enabled'],
				'label'    => '' === $label ? $field->label : $label,
				'options'  => $options,
			];
		}

		return $mapping;
	}

	/**
	 * Gets the requested field order.
	 *
	 * @param array<string, mixed> $request          Request data.
	 * @param FieldCollection      $available_fields Available field definitions.
	 *
	 * @return array<int, string>
	 */
	private function get_field_order_from_request( array $request, FieldCollection $available_fields ): array {
		$available = $available_fields->all();
		$raw_order = $request['storeaccountant_field_mapping_order'] ?? [];
		$raw_order = is_array( $raw_order ) ? wp_unslash( $raw_order ) : [];
		$order     = [];

		foreach ( $raw_order as $field_id ) {
			$field_id = is_scalar( $field_id ) ? sanitize_key( (string) $field_id ) : '';

			if ( '' === $field_id || ! isset( $available[ $field_id ] ) || in_array( $field_id, $order, true ) ) {
				continue;
			}

			$order[] = $field_id;
		}

		foreach ( $available as $field_id => $field ) {
			if ( in_array( $field_id, $order, true ) ) {
				continue;
			}

			$order[] = $field_id;
		}

		return $order;
	}

	/**
	 * Saves field mapping items.
	 *
	 * @param int               $configuration_id Export configuration post ID.
	 * @param array<int, mixed> $mapping          Mapping items.
	 */
	public function save( int $configuration_id, array $mapping ): void {
		update_post_meta( $configuration_id, ExportConfigurationPostType::META_FIELD_MAPPING, wp_json_encode( $mapping ) );
	}

	/**
	 * Replaces only matching mapping items with matching available fields.
	 *
	 * @param int             $configuration_id Export configuration post ID.
	 * @param FieldCollection $available_fields Available field definitions.
	 * @param callable        $matches          Callback receiving a field ID.
	 */
	public function refresh_matching_fields( int $configuration_id, FieldCollection $available_fields, callable $matches ): void {
		$stored    = $this->get_mapping( $configuration_id );
		$available = $available_fields->all();
		$refreshed = [];
		$inserted  = false;

		if ( [] === $stored ) {
			return;
		}

		foreach ( $stored as $item ) {
			$field_id = isset( $item['field_id'] ) && is_string( $item['field_id'] ) ? $item['field_id'] : '';

			if ( '' !== $field_id && true === $matches( $field_id ) ) {
				if ( ! $inserted ) {
					$refreshed = array_merge( $refreshed, $this->get_default_matching_items( $available, $matches ) );
					$inserted  = true;
				}

				continue;
			}

			$refreshed[] = $item;
		}

		if ( ! $inserted ) {
			$refreshed = array_merge( $refreshed, $this->get_default_matching_items( $available, $matches ) );
		}

		$this->save( $configuration_id, $refreshed );
	}

	/**
	 * Gets stored field mapping items.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_mapping( int $configuration_id ): array {
		$mapping = json_decode( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_FIELD_MAPPING, true ), true );

		return is_array( $mapping ) ? array_values( array_filter( $mapping, 'is_array' ) ) : [];
	}

	/**
	 * Checks whether a mapping item is enabled.
	 *
	 * @param array<string, mixed> $item Mapping item.
	 */
	private function is_enabled( array $item ): bool {
		return true === ( $item['enabled'] ?? false );
	}

	/**
	 * Gets the configured field label.
	 *
	 * @param array<string, mixed> $item  Mapping item.
	 * @param Field                $field Available field.
	 */
	private function get_label( array $item, Field $field ): string {
		$label = isset( $item['label'] ) && is_string( $item['label'] ) ? trim( $item['label'] ) : '';

		return '' === $label ? $field->label : $label;
	}

	/**
	 * Gets field options from the mapping.
	 *
	 * @param array<string, mixed> $item  Mapping item.
	 * @param Field                $field Available field.
	 *
	 * @return array<string, mixed>
	 */
	private function get_options( array $item, Field $field ): array {
		$options = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : [];

		return array_merge( $field->options, $options );
	}

	/**
	 * Finds a mapping item by field ID.
	 *
	 * @param array<int, array<string, mixed>> $mapping  Mapping items.
	 * @param string                           $field_id Field identifier.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_mapping_item( array $mapping, string $field_id ): ?array {
		foreach ( $mapping as $item ) {
			if ( isset( $item['field_id'] ) && $field_id === $item['field_id'] ) {
				return $item;
			}
		}

		return null;
	}

	/**
	 * Checks whether mapping contains a field ID.
	 *
	 * @param array<int, array<string, mixed>> $mapping  Mapping items.
	 * @param string                           $field_id Field identifier.
	 */
	private function has_mapping_item( array $mapping, string $field_id ): bool {
		return null !== $this->find_mapping_item( $mapping, $field_id );
	}

	/**
	 * Sanitizes option values.
	 *
	 * @param array<string, mixed> $options Raw option values.
	 *
	 * @return array<string, mixed>
	 */
	private function sanitize_options( array $options ): array {
		$sanitized = [];

		foreach ( $options as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$sanitized[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
		}

		return $sanitized;
	}

	/**
	 * Gets default mapping items for matching fields.
	 *
	 * @param array<string, Field> $available Available field definitions.
	 * @param callable             $matches   Callback receiving a field ID.
	 *
	 * @return array<int, array{field_id: string, enabled: bool, label: string, options: array<string, mixed>}>
	 */
	private function get_default_matching_items( array $available, callable $matches ): array {
		$items = [];

		foreach ( $available as $field_id => $field ) {
			if ( true !== $matches( $field_id ) ) {
				continue;
			}

			$items[] = [
				'field_id' => $field_id,
				'enabled'  => true,
				'label'    => $field->label,
				'options'  => [],
			];
		}

		return $items;
	}

	/**
	 * Moves metadata-backed fields to the end of a field list.
	 *
	 * @param array<string, Field> $fields Fields keyed by ID.
	 *
	 * @return array<string, Field>
	 */
	private function move_meta_fields_to_end( array $fields ): array {
		$regular_fields = [];
		$meta_fields    = [];

		foreach ( $fields as $field_id => $field ) {
			if ( MetaField::is_meta_field( $field ) ) {
				$meta_fields[ $field_id ] = $field;
				continue;
			}

			$regular_fields[ $field_id ] = $field;
		}

		return array_merge( $regular_fields, $meta_fields );
	}

	/**
	 * Moves metadata-backed form items to the end of the mapping form.
	 *
	 * @param array<int, array{field_id: string, enabled: bool, label: string, options: array<string, mixed>}> $items     Mapping form items.
	 * @param array<string, Field>                                                                             $available Available fields.
	 *
	 * @return array<int, array{field_id: string, enabled: bool, label: string, options: array<string, mixed>}>
	 */
	private function move_meta_items_to_end( array $items, array $available ): array {
		$regular_items = [];
		$meta_items    = [];

		foreach ( $items as $item ) {
			$field_id = $item['field_id'];
			$field    = $available[ $field_id ] ?? null;

			if ( $field instanceof Field && MetaField::is_meta_field( $field ) ) {
				$meta_items[] = $item;
				continue;
			}

			$regular_items[] = $item;
		}

		return array_merge( $regular_items, $meta_items );
	}
}
