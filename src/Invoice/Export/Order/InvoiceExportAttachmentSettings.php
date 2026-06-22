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

namespace StoreAccountant\Invoice\Export\Order;

use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Invoice\Contract\InvoicePluginInterface;
use function array_filter;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads invoice attachment settings from order export configurations.
 */
final readonly class InvoiceExportAttachmentSettings {
	public const PROVIDER_ID         = 'invoice_attachments';
	public const OPTION_EXPORT_FILES = 'export_files';
	public const OPTION_FILE_TYPES   = 'file_types';

	/**
	 * Checks whether invoice files should be exported.
	 *
	 * @param int                    $configuration_id Export configuration post ID.
	 * @param InvoicePluginInterface $plugin           Invoice plugin integration.
	 * @param int                    $export_id        Export post ID.
	 */
	public function is_enabled( int $configuration_id, InvoicePluginInterface $plugin, int $export_id = 0 ): bool {
		return [] !== $this->get_selected_file_types( $configuration_id, $plugin, $export_id );
	}

	/**
	 * Gets selected invoice file types.
	 *
	 * @param int                    $configuration_id Export configuration post ID.
	 * @param InvoicePluginInterface $plugin           Invoice plugin integration.
	 * @param int                    $export_id        Export post ID.
	 *
	 * @return array<int, string>
	 */
	public function get_selected_file_types( int $configuration_id, InvoicePluginInterface $plugin, int $export_id = 0 ): array {
		$settings = $this->get_settings( $configuration_id, $export_id );
		$selected = $settings[ self::OPTION_FILE_TYPES ] ?? [];

		if ( ! is_array( $selected ) ) {
			return [];
		}

		$available = [];

		foreach ( $plugin->get_invoice_file_types() as $type ) {
			$available[] = $type->id;
		}

		if ( [] !== $selected ) {
			return array_values(
				array_filter(
					$selected,
					static fn ( mixed $type ): bool => is_string( $type ) && in_array( $type, $available, true )
				)
			);
		}

		if ( true === ( $settings[ self::OPTION_EXPORT_FILES ] ?? false ) ) {
			return $available;
		}

		return [];
	}

	/**
	 * Gets stored invoice attachment settings.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 * @param int $export_id        Export post ID.
	 *
	 * @return array<string, mixed>
	 */
	private function get_settings( int $configuration_id, int $export_id ): array {
		if ( $configuration_id <= 0 && $export_id <= 0 ) {
			return [];
		}

		$meta_source_id = $configuration_id > 0 ? $configuration_id : $export_id;
		$meta_key       = $configuration_id > 0 ? ExportConfigurationPostType::META_ADDITIONAL_SETTINGS : ExportPostType::META_ADDITIONAL_SETTINGS;

		$additional_settings = json_decode( (string) get_post_meta( $meta_source_id, $meta_key, true ), true );

		if ( ! is_array( $additional_settings ) ) {
			return [];
		}

		$settings = $additional_settings[ self::PROVIDER_ID ] ?? [];

		return is_array( $settings ) ? $settings : [];
	}
}
