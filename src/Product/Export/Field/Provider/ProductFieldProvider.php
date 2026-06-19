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
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Type\BooleanFieldType;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use function add_filter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce product export fields.
 */
final readonly class ProductFieldProvider implements FieldProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'products';

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
		$fields = [
			'product_id'         => new Field( 'product_id', 'product_id', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'parent_product_id'  => new Field( 'parent_product_id', 'parent_product_id', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'name'               => new Field( 'name', 'name' ),
			'slug'               => new Field( 'slug', 'slug' ),
			'sku'                => new Field( 'sku', 'sku' ),
			'parent_sku'         => new Field( 'parent_sku', 'parent_sku' ),
			'product_type'       => new Field( 'product_type', 'product_type' ),
			'status'             => new Field( 'status', 'status' ),
			'catalog_visibility' => new Field( 'catalog_visibility', 'catalog_visibility' ),
			'date_created'       => new Field( 'date_created', 'date_created', new DateTimeFieldType() ),
			'date_modified'      => new Field( 'date_modified', 'date_modified', new DateTimeFieldType() ),
			'permalink'          => new Field( 'permalink', 'permalink' ),
			'menu_order'         => new Field( 'menu_order', 'menu_order', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'total_sales'        => new Field( 'total_sales', 'total_sales', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
			'average_rating'     => new Field( 'average_rating', 'average_rating', new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) ),
			'review_count'       => new Field( 'review_count', 'review_count', new NumberFieldType( NumberFieldType::FORMAT_INTEGER ) ),
		];

		foreach ( [
			'featured',
			'virtual',
			'downloadable',
			'manage_stock',
			'sold_individually',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id, new BooleanFieldType() );
		}

		foreach ( [
			'regular_price',
			'sale_price',
			'price',
			'stock_quantity',
			'weight',
			'length',
			'width',
			'height',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id, new NumberFieldType( NumberFieldType::FORMAT_DECIMAL ) );
		}

		foreach ( [
			'tax_status',
			'tax_class',
			'stock_status',
			'backorders',
			'categories',
			'tags',
			'attributes',
			'default_attributes',
			'variation_attributes',
			'description',
			'short_description',
		] as $id ) {
			$fields[ $id ] = new Field( $id, $id );
		}

		return $fields;
	}
}
