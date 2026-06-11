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

namespace StoreAccountant\Export\Contract;

use WP_Error;
use StoreAccountant\Contract\RegistryItemInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines a service that exports normalized data.
 */
interface ExportAdapterInterface extends RegistryItemInterface {
	/**
	 * Gets source items for a saved export.
	 *
	 * @param ExportPayload $payload Export payload.
	 *
	 * @return iterable<mixed>|WP_Error
	 */
	public function get_items( ExportPayload $payload ): iterable|WP_Error;

	/**
	 * Gets runtime context for fields and value providers.
	 *
	 * @param ExportPayload   $payload Export payload.
	 * @param iterable<mixed> $items   Source items.
	 */
	public function get_context( ExportPayload $payload, iterable $items ): ExportContext;

	/**
	 * Gets adapter-specific additional fields.
	 *
	 * @param ExportPayload $payload Export payload.
	 * @param ExportContext $context Export context.
	 */
	public function get_additional_fields( ExportPayload $payload, ExportContext $context ): FieldCollection;

	/**
	 * Gets adapter-specific additional values for a source item.
	 *
	 * @param mixed         $item    Source item.
	 * @param ExportPayload $payload Export payload.
	 * @param ExportContext $context Export context.
	 *
	 * @return array<string, FieldValue>
	 */
	public function get_additional_values( mixed $item, ExportPayload $payload, ExportContext $context ): array;

	/**
	 * Gets a stable record ID for a source item.
	 *
	 * @param mixed $item Source item.
	 */
	public function get_record_id( mixed $item ): string;
}
