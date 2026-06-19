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

namespace StoreAccountant\Product\Export\Field\Provider;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Meta\MetaField;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use function add_filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides export fields for custom WooCommerce product meta.
 */
final readonly class ProductMetaFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID     = 'product_meta';
	public const FIELD_ID_PREFIX = 'product_meta_';
	public const OPTION_META_KEY = MetaField::OPTION_META_KEY;

	/**
	 * WooCommerce product meta keys already represented by dedicated export fields.
	 *
	 * @var array<int, string>
	 */
	private const RESERVED_META_KEYS = [
		'_backorders',
		'_catalog_visibility',
		'_crosssell_ids',
		'_default_attributes',
		'_download_expiry',
		'_download_limit',
		'_downloadable',
		'_downloadable_files',
		'_edit_lock',
		'_featured',
		'_height',
		'_length',
		'_manage_stock',
		'_price',
		'_product_attributes',
		'_product_image_gallery',
		'_product_version',
		'_purchase_note',
		'_regular_price',
		'_sale_price',
		'_sku',
		'_sold_individually',
		'_stock',
		'_stock_status',
		'_tax_class',
		'_tax_status',
		'_thumbnail_id',
		'_virtual',
		'_wc_average_rating',
		'_wc_review_count',
		'_weight',
		'_width',
		'total_sales',
	];

	/**
	 * Initializes the product meta field provider.
	 *
	 * @param MetaFieldCollector $collector Metadata field collector.
	 */
	public function __construct(
		private MetaFieldCollector $collector
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id(): string {
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function supports( ExportContext $context ): bool {
		return ProductExportAdapter::ADAPTER_ID === $context->export_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_fields( ExportContext $context ): array {
		return $this->collector->get_fields( $context, self::FIELD_ID_PREFIX, self::RESERVED_META_KEYS );
	}

	/**
	 * Gets the stable export field ID for a product meta key.
	 *
	 * @param string $meta_key WooCommerce product meta key.
	 */
	public static function get_field_id( string $meta_key ): string {
		return MetaField::get_field_id( self::FIELD_ID_PREFIX, $meta_key );
	}
}
