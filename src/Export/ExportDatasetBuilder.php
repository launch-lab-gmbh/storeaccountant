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

use WP_Error;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\Dataset\ExportDataset;
use StoreAccountant\Export\Dataset\ExportRecord;
use StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds normalized export datasets from fields, value providers, and mappings.
 */
final readonly class ExportDatasetBuilder {
	/**
	 * Initializes the export dataset builder.
	 *
	 * @param FieldValueProviderRegistry       $field_value_providers Field value provider registry.
	 * @param FieldValueMutatorRegistry        $field_value_mutators  Field value mutator registry.
	 * @param ExportFieldResolver              $field_resolver        Export field resolver.
	 * @param ExportAttachmentProviderRegistry $attachment_providers Export attachment provider registry.
	 */
	public function __construct(
		private FieldValueProviderRegistry $field_value_providers,
		private FieldValueMutatorRegistry $field_value_mutators,
		private ExportFieldResolver $field_resolver,
		private ExportAttachmentProviderRegistry $attachment_providers
	) {}

	/**
	 * Builds an export dataset for an adapter payload.
	 *
	 * @param ExportAdapterInterface $adapter Export adapter.
	 * @param ExportPayload          $payload Export payload.
	 *
	 * @return ExportDataset|WP_Error
	 */
	public function build( ExportAdapterInterface $adapter, ExportPayload $payload ): ExportDataset|WP_Error {
		$items = $adapter->get_items( $payload );

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		return $this->build_from_items( $adapter, $payload, $items );
	}

	/**
	 * Builds an export dataset for an already loaded source item batch.
	 *
	 * @param ExportAdapterInterface $adapter Export adapter.
	 * @param ExportPayload          $payload Export payload.
	 * @param iterable<mixed>        $items   Source items.
	 *
	 * @return ExportDataset|WP_Error
	 */
	public function build_from_items( ExportAdapterInterface $adapter, ExportPayload $payload, iterable $items ): ExportDataset|WP_Error {
		if ( ! is_array( $items ) ) {
			$items = iterator_to_array( $items, false );
		}

		$export_type          = $adapter->get_id();
		$context              = $adapter->get_context( $payload, $items );
		$fields               = $this->field_resolver->get_fields_for_payload( $adapter, $payload, $context );
		$value_providers      = $this->field_value_providers->get_providers( $fields, $context );
		$value_mutators       = $this->field_value_mutators->get_all();
		$attachment_providers = true === ( $payload->options[ ExportPayload::OPTION_INCLUDE_ATTACHMENTS ] ?? false )
			? $this->attachment_providers->get_providers( $context )
			: [];

		return new ExportDataset(
			$fields,
			$this->get_records(
				$items,
				$fields,
				$value_providers,
				$value_mutators,
				$context,
				$adapter,
				$payload
			),
			$this->get_attachments( $items, $attachment_providers, $payload, $context ),
			[
				'type' => $export_type,
			]
		);
	}

	/**
	 * Gets a dataset record for a source item.
	 *
	 * @param mixed                                      $item                       Source item.
	 * @param FieldCollection                            $fields                     Dataset fields.
	 * @param array<string, FieldValueProviderInterface> $providers Field value providers.
	 * @param array<string, FieldValueMutatorInterface>  $mutators  Field value mutators.
	 * @param ExportContext                              $context                    Export context.
	 * @param ExportAdapterInterface                     $adapter                    Export adapter.
	 * @param ExportPayload                              $payload                    Export payload.
	 */
	private function get_record(
		mixed $item,
		FieldCollection $fields,
		array $providers,
		array $mutators,
		ExportContext $context,
		ExportAdapterInterface $adapter,
		ExportPayload $payload
	): ExportRecord {
		$values            = [];
		$field_definitions = $fields->all();

		foreach ( $providers as $provider ) {
			$supported_fields = [];

			foreach ( $field_definitions as $field ) {
				if ( $provider->supports( $field, $context ) ) {
					$supported_fields[ $field->id ] = $field;
				}
			}

			foreach ( $provider->get_values( $item, new FieldCollection( $supported_fields ), $context ) as $value ) {
				$values[ $value->field_id ] = $value;
			}
		}

		foreach ( $adapter->get_additional_values( $item, $payload, $context ) as $value ) {
			$values[ $value->field_id ] = $value;
		}

		$record_values = [];

		foreach ( $fields->ids() as $field_id ) {
			$value = $values[ $field_id ] ?? new FieldValue( $field_id, '' );
			$field = $field_definitions[ $field_id ] ?? null;

			if ( null !== $field ) {
				foreach ( $mutators as $mutator ) {
					if ( $mutator->supports( $field, $context ) ) {
						// Mutators are intentionally chained: each mutator receives the previous result.
						$value = $mutator->mutate( $value, $field, $field->options, $context );
					}
				}
			}

			$record_values[] = $value;
		}

		return new ExportRecord( $adapter->get_record_id( $item ), $record_values );
	}

	/**
	 * Lazily gets export records for source items.
	 *
	 * @param array<int, mixed>                                $items     Source items.
	 * @param FieldCollection                                  $fields    Dataset fields.
	 * @param array<string, FieldValueProviderInterface>       $providers Field value providers.
	 * @param array<string, FieldValueMutatorInterface>        $mutators  Field value mutators.
	 * @param ExportContext                                    $context   Export context.
	 * @param ExportAdapterInterface                           $adapter   Export adapter.
	 * @param ExportPayload                                    $payload   Export payload.
	 *
	 * @return iterable<ExportRecord>
	 */
	private function get_records(
		array $items,
		FieldCollection $fields,
		array $providers,
		array $mutators,
		ExportContext $context,
		ExportAdapterInterface $adapter,
		ExportPayload $payload
	): iterable {
		foreach ( $items as $item ) {
			yield $this->get_record(
				$item,
				$fields,
				$providers,
				$mutators,
				$context,
				$adapter,
				$payload
			);
		}
	}

	/**
	 * Lazily gets export attachments for source items.
	 *
	 * @param array<int, mixed>                                $items     Source items.
	 * @param array<string, ExportAttachmentProviderInterface> $providers Attachment providers.
	 * @param ExportPayload                                    $payload   Export payload.
	 * @param ExportContext                                    $context   Export context.
	 *
	 * @return iterable<mixed>
	 */
	private function get_attachments( array $items, array $providers, ExportPayload $payload, ExportContext $context ): iterable {
		foreach ( $items as $item ) {
			foreach ( $providers as $provider ) {
				foreach ( $provider->get_attachments( $item, $payload, $context ) as $attachment ) {
					yield $attachment;
				}
			}
		}
	}
}
