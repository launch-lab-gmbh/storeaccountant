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

use WC_DateTime;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use function add_filter;
use function array_filter;
use function array_map;
use function array_values;
use function function_exists;
use function get_permalink;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_scalar;
use function method_exists;
use function number_format;
use function str_replace;
use function str_starts_with;
use function substr;
use function trim;
use function wc_attribute_label;
use function wc_get_product;
use function wc_get_product_terms;
use function wp_strip_all_tags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides WooCommerce product export field values.
 */
final readonly class ProductFieldValueProvider implements FieldValueProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'products';

	/**
	 * Field IDs resolved by this provider.
	 *
	 * @var array<int, string>
	 */
	private const SUPPORTED_FIELD_IDS = [
		'product_id',
		'parent_product_id',
		'name',
		'slug',
		'sku',
		'parent_sku',
		'product_type',
		'status',
		'catalog_visibility',
		'date_created',
		'date_modified',
		'permalink',
		'menu_order',
		'total_sales',
		'average_rating',
		'review_count',
		'featured',
		'virtual',
		'downloadable',
		'manage_stock',
		'sold_individually',
		'regular_price',
		'sale_price',
		'price',
		'stock_quantity',
		'weight',
		'length',
		'width',
		'height',
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
	];

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_field_value_provider',
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
	public function supports( Field $field, ExportContext $context ): bool {
		return ProductExportAdapter::ADAPTER_ID === $context->export_type && in_array( $field->id, self::SUPPORTED_FIELD_IDS, true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
		if ( ProductExportAdapter::ADAPTER_ID !== $context->export_type || ! $item instanceof WC_Product ) {
			return [];
		}

		$parent = $this->get_parent_product( $item );
		$values = [
			'product_id'           => new FieldValue( 'product_id', $item->get_id() ),
			'parent_product_id'    => new FieldValue( 'parent_product_id', $item->get_parent_id() ),
			'name'                 => new FieldValue( 'name', $item->get_name() ),
			'slug'                 => new FieldValue( 'slug', $item->get_slug() ),
			'sku'                  => new FieldValue( 'sku', $item->get_sku() ),
			'parent_sku'           => new FieldValue( 'parent_sku', null !== $parent ? $parent->get_sku() : '' ),
			'product_type'         => new FieldValue( 'product_type', $item->get_type() ),
			'status'               => new FieldValue( 'status', $item->get_status() ),
			'catalog_visibility'   => new FieldValue( 'catalog_visibility', $item->get_catalog_visibility() ),
			'date_created'         => new FieldValue( 'date_created', $this->format_datetime( $item->get_date_created() ) ),
			'date_modified'        => new FieldValue( 'date_modified', $this->format_datetime( $item->get_date_modified() ) ),
			'permalink'            => new FieldValue( 'permalink', $this->get_permalink( $item ) ),
			'menu_order'           => new FieldValue( 'menu_order', $item->get_menu_order() ),
			'total_sales'          => new FieldValue( 'total_sales', $item->get_total_sales() ),
			'average_rating'       => new FieldValue( 'average_rating', $this->format_amount( $item->get_average_rating() ) ),
			'review_count'         => new FieldValue( 'review_count', $item->get_review_count() ),
			'featured'             => new FieldValue( 'featured', $this->format_bool( $item->get_featured() ) ),
			'virtual'              => new FieldValue( 'virtual', $this->format_bool( $item->get_virtual() ) ),
			'downloadable'         => new FieldValue( 'downloadable', $this->format_bool( $item->get_downloadable() ) ),
			'manage_stock'         => new FieldValue( 'manage_stock', $this->format_bool( $item->get_manage_stock() ) ),
			'sold_individually'    => new FieldValue( 'sold_individually', $this->format_bool( $item->get_sold_individually() ) ),
			'regular_price'        => new FieldValue( 'regular_price', $this->format_amount( $item->get_regular_price() ) ),
			'sale_price'           => new FieldValue( 'sale_price', $this->format_amount( $item->get_sale_price() ) ),
			'price'                => new FieldValue( 'price', $this->format_amount( $item->get_price() ) ),
			'stock_quantity'       => new FieldValue( 'stock_quantity', $this->format_amount( $item->get_stock_quantity() ) ),
			'weight'               => new FieldValue( 'weight', $this->format_amount( $item->get_weight() ) ),
			'length'               => new FieldValue( 'length', $this->format_amount( $item->get_length() ) ),
			'width'                => new FieldValue( 'width', $this->format_amount( $item->get_width() ) ),
			'height'               => new FieldValue( 'height', $this->format_amount( $item->get_height() ) ),
			'tax_status'           => new FieldValue( 'tax_status', $item->get_tax_status() ),
			'tax_class'            => new FieldValue( 'tax_class', $item->get_tax_class() ),
			'stock_status'         => new FieldValue( 'stock_status', $item->get_stock_status() ),
			'backorders'           => new FieldValue( 'backorders', $item->get_backorders() ),
			'categories'           => new FieldValue( 'categories', $this->format_terms( $this->get_taxonomy_product( $item ), 'product_cat' ) ),
			'tags'                 => new FieldValue( 'tags', $this->format_terms( $this->get_taxonomy_product( $item ), 'product_tag' ) ),
			'attributes'           => new FieldValue( 'attributes', $this->format_attributes( $item ) ),
			'default_attributes'   => new FieldValue( 'default_attributes', $this->format_key_value_list( $item->get_default_attributes() ) ),
			'variation_attributes' => new FieldValue( 'variation_attributes', $this->format_variation_attributes( $item ) ),
			'description'          => new FieldValue( 'description', wp_strip_all_tags( $item->get_description() ) ),
			'short_description'    => new FieldValue( 'short_description', wp_strip_all_tags( $item->get_short_description() ) ),
		];

		return $fields->filter_values( $values );
	}

	/**
	 * Gets the parent product for a variation.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function get_parent_product( WC_Product $product ): ?WC_Product {
		$parent_id = $product->get_parent_id();

		if ( $parent_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$parent = wc_get_product( $parent_id );

		return $parent instanceof WC_Product ? $parent : null;
	}

	/**
	 * Gets the product whose taxonomy terms should be exported.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function get_taxonomy_product( WC_Product $product ): WC_Product {
		return $product instanceof WC_Product_Variation ? ( $this->get_parent_product( $product ) ?? $product ) : $product;
	}

	/**
	 * Gets a product permalink.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function get_permalink( WC_Product $product ): string {
		$permalink = get_permalink( $product->get_id() );

		return false === $permalink ? '' : $permalink;
	}

	/**
	 * Formats a WooCommerce date for export output.
	 */
	private function format_datetime( ?WC_DateTime $date ): string {
		if ( null === $date ) {
			return '';
		}

		return $date->date( 'Y-m-d H:i:s' );
	}

	/**
	 * Formats an amount-like product value.
	 *
	 * @param mixed $amount Product amount.
	 */
	private function format_amount( mixed $amount ): string {
		if ( null === $amount || '' === $amount || ! is_numeric( $amount ) ) {
			return '';
		}

		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Formats a boolean product value.
	 */
	private function format_bool( bool $value ): string {
		return $value ? '1' : '0';
	}

	/**
	 * Formats product taxonomy terms.
	 *
	 * @param WC_Product $product  WooCommerce product.
	 * @param string     $taxonomy Taxonomy name.
	 */
	private function format_terms( WC_Product $product, string $taxonomy ): string {
		if ( ! function_exists( 'wc_get_product_terms' ) ) {
			return '';
		}

		$terms = wc_get_product_terms(
			$product->get_id(),
			$taxonomy,
			[
				'fields' => 'names',
			]
		);

		return is_array( $terms ) ? implode( ', ', array_map( 'strval', $terms ) ) : '';
	}

	/**
	 * Formats product attributes.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function format_attributes( WC_Product $product ): string {
		$attributes = [];

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute instanceof WC_Product_Attribute ) {
				continue;
			}

			$name   = $this->get_attribute_label( $attribute->get_name(), $product );
			$values = $attribute->is_taxonomy()
				? $this->get_attribute_term_names( $product, $attribute->get_name() )
				: array_values( array_filter( array_map( 'strval', $attribute->get_options() ) ) );

			if ( '' === $name || [] === $values ) {
				continue;
			}

			$attributes[] = $name . ': ' . implode( ', ', $values );
		}

		return implode( '; ', $attributes );
	}

	/**
	 * Formats variation attributes.
	 *
	 * @param WC_Product $product WooCommerce product.
	 */
	private function format_variation_attributes( WC_Product $product ): string {
		if ( ! $product instanceof WC_Product_Variation || ! method_exists( $product, 'get_variation_attributes' ) ) {
			return '';
		}

		return $this->format_key_value_list( $product->get_variation_attributes() );
	}

	/**
	 * Gets attribute term names.
	 *
	 * @param WC_Product $product   WooCommerce product.
	 * @param string     $taxonomy  Attribute taxonomy.
	 *
	 * @return array<int, string>
	 */
	private function get_attribute_term_names( WC_Product $product, string $taxonomy ): array {
		if ( ! function_exists( 'wc_get_product_terms' ) ) {
			return [];
		}

		$terms = wc_get_product_terms(
			$product->get_id(),
			$taxonomy,
			[
				'fields' => 'names',
			]
		);

		return is_array( $terms ) ? array_values( array_map( 'strval', $terms ) ) : [];
	}

	/**
	 * Formats an associative list for export.
	 *
	 * @param array<string, mixed> $values Key-value list.
	 */
	private function format_key_value_list( array $values ): string {
		$formatted = [];

		foreach ( $values as $key => $value ) {
			$key   = $this->normalize_attribute_key( (string) $key );
			$value = is_scalar( $value ) ? trim( (string) $value ) : '';

			if ( '' === $key || '' === $value ) {
				continue;
			}

			$formatted[] = $this->get_attribute_label( $key ) . ': ' . $value;
		}

		return implode( '; ', $formatted );
	}

	/**
	 * Gets a readable attribute label.
	 *
	 * @param string          $attribute Attribute key.
	 * @param WC_Product|null $product   Product context.
	 */
	private function get_attribute_label( string $attribute, ?WC_Product $product = null ): string {
		$attribute = $this->normalize_attribute_key( $attribute );

		if ( '' === $attribute ) {
			return '';
		}

		if ( function_exists( 'wc_attribute_label' ) ) {
			return wc_attribute_label( $attribute, $product );
		}

		return str_replace( [ 'pa_', '_' ], [ '', ' ' ], $attribute );
	}

	/**
	 * Normalizes a WooCommerce attribute key.
	 */
	private function normalize_attribute_key( string $attribute ): string {
		if ( str_starts_with( $attribute, 'attribute_' ) ) {
			$attribute = substr( $attribute, 10 );
		}

		return trim( $attribute );
	}
}
