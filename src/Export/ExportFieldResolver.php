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

namespace StoreAccountant\Export;

use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Meta\MetaField;
use function array_merge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves mapped export fields for export adapters and admin forms.
 */
final readonly class ExportFieldResolver {
	/**
	 * Initializes the export field resolver.
	 *
	 * @param FieldProviderRegistry  $field_providers Field provider registry.
	 * @param FieldMappingRepository $field_mapping   Field mapping repository.
	 */
	public function __construct(
		private FieldProviderRegistry $field_providers,
		private FieldMappingRepository $field_mapping
	) {}

	/**
	 * Gets mapped fields for an adapter payload.
	 *
	 * @param ExportAdapterInterface $adapter Export adapter.
	 * @param ExportPayload          $payload Export payload.
	 * @param ExportContext          $context Export context.
	 */
	public function get_fields_for_payload( ExportAdapterInterface $adapter, ExportPayload $payload, ExportContext $context ): FieldCollection {
		return $this->get_fields(
			$context,
			$adapter->get_additional_fields( $payload, $context )
		);
	}

	/**
	 * Gets mapped fields for an export type.
	 *
	 * @param ExportContext        $context           Export context.
	 * @param FieldCollection|null $additional_fields Adapter-provided additional fields.
	 */
	public function get_fields( ExportContext $context, ?FieldCollection $additional_fields = null ): FieldCollection {
		$field_definitions = $this->field_providers->get_fields( $context )->all();

		if ( null !== $additional_fields ) {
			$field_definitions = array_merge( $field_definitions, $additional_fields->all() );
		}

		return $this->field_mapping->get_mapped_fields(
			$context->configuration_id,
			new FieldCollection( $this->move_meta_fields_to_end( $field_definitions ) )
		);
	}

	/**
	 * Moves custom metadata fields behind all fixed and adapter-provided fields.
	 *
	 * @param array<string, \StoreAccountant\Export\Field\Field> $fields Field definitions.
	 *
	 * @return array<string, \StoreAccountant\Export\Field\Field>
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
}
