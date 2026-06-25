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

namespace StoreAccountant\Export\Configuration;

use WP_Error;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists reusable export configurations.
 */
final readonly class ExportConfigurationRepository {
	/**
	 * Initializes the repository.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportFilterSelectionSerializer $filter_serializer Filter selection serializer.
	 */
	public function __construct(
		private ExportFilterSelectionSerializer $filter_serializer
	) {}

	/**
	 * Creates a saved export configuration.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param string                            $title               Configuration title.
	 * @param array<int, ExportFilterSelection> $filters Configured export filters.
	 * @param string                            $export_adapter      Export adapter identifier.
	 * @param string                            $export_writer       Export writer identifier.
	 * @param string                            $storage_engine      storage adapter identifier.
	 * @param array<string, mixed>              $additional_settings Additional provider settings.
	 * @param int                               $batch_size          Number of source items to process per batch.
	 *
	 * @return int|WP_Error
	 */
	public function create(
		string $title,
		array $filters,
		string $export_adapter,
		string $export_writer,
		string $storage_engine,
		array $additional_settings,
		int $batch_size = ExportPostType::DEFAULT_BATCH_SIZE
	): int|WP_Error {
		$meta_input = [
			ExportConfigurationPostType::META_FILTERS    => $this->filter_serializer->encode( $filters ),
			ExportConfigurationPostType::META_EXPORT_ADAPTER => $export_adapter,
			ExportConfigurationPostType::META_EXPORT_WRITER => $export_writer,
			ExportConfigurationPostType::META_STORAGE_ENGINE => $storage_engine,
			ExportConfigurationPostType::META_BATCH_SIZE => (string) max( ExportPostType::MIN_BATCH_SIZE, $batch_size ),
			ExportConfigurationPostType::META_ADDITIONAL_SETTINGS => wp_json_encode( $additional_settings ),
		];

		return wp_insert_post(
			[
				'post_type'   => ExportConfigurationPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => $meta_input,
			],
			true
		);
	}

	/**
	 * Updates a saved export configuration.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param int                               $post_id             Configuration post ID.
	 * @param string                            $title               Configuration title.
	 * @param array<int, ExportFilterSelection> $filters Configured export filters.
	 * @param string                            $export_adapter      Export adapter identifier.
	 * @param string                            $export_writer       Export writer identifier.
	 * @param string                            $storage_engine      storage adapter identifier.
	 * @param array<string, mixed>              $additional_settings Additional provider settings.
	 * @param int                               $batch_size          Number of source items to process per batch.
	 *
	 * @return int|WP_Error
	 */
	public function update(
		int $post_id,
		string $title,
		array $filters,
		string $export_adapter,
		string $export_writer,
		string $storage_engine,
		array $additional_settings,
		int $batch_size = ExportPostType::DEFAULT_BATCH_SIZE
	): int|WP_Error {
		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_type'   => ExportConfigurationPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $post_id, ExportConfigurationPostType::META_FILTERS, $this->filter_serializer->encode( $filters ) );
		update_post_meta( $post_id, ExportConfigurationPostType::META_EXPORT_ADAPTER, $export_adapter );
		update_post_meta( $post_id, ExportConfigurationPostType::META_EXPORT_WRITER, $export_writer );
		update_post_meta( $post_id, ExportConfigurationPostType::META_STORAGE_ENGINE, $storage_engine );
		update_post_meta( $post_id, ExportConfigurationPostType::META_BATCH_SIZE, (string) max( ExportPostType::MIN_BATCH_SIZE, $batch_size ) );
		update_post_meta( $post_id, ExportConfigurationPostType::META_ADDITIONAL_SETTINGS, wp_json_encode( $additional_settings ) );

		return $post_id;
	}
}
