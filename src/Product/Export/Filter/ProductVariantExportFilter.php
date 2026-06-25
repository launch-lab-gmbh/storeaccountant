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

namespace StoreAccountant\Product\Export\Filter;

use WP_Error;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\Contract\ExportFilterInterface;
use StoreAccountant\Export\Filter\ExportFilterSelection;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Product\Export\Query\ProductQueryCriteria;
use function __;
use function add_filter;
use function is_scalar;
use function sanitize_key;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controls whether product variations are exported as separate rows.
 */
final readonly class ProductVariantExportFilter implements ExportFilterInterface, HookRegistrarInterface {
	public const FILTER_ID              = 'product_variant_export';
	public const MODE_PARENT_PRODUCTS   = 'parent_products';
	public const MODE_SEPARATE_VARIANTS = 'separate_variants';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_filter',
			function ( array $filters ): array {
				$filters[ self::FILTER_ID ] = $this;

				return $filters;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_id(): string {
		return self::FILTER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( string $export_type ): bool {
		return ProductExportAdapter::ADAPTER_ID === $export_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function apply( mixed $query, ExportFilterSelection $selection, ExportPayload $payload ): true|WP_Error {
		if ( ! $query instanceof ProductQueryCriteria ) {
			return new WP_Error( 'storeaccountant_invalid_product_query', __( 'The product variant setting requires a WooCommerce product query.', 'storeaccountant' ) );
		}

		$query->export_variations = self::MODE_SEPARATE_VARIANTS === self::sanitize_mode( $selection->settings['mode'] ?? self::MODE_PARENT_PRODUCTS );

		return true;
	}

	/**
	 * Gets available variant export modes.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @return array<string, string>
	 */
	public static function get_modes(): array {
		return [
			self::MODE_PARENT_PRODUCTS   => __( 'Parent products only', 'storeaccountant' ),
			self::MODE_SEPARATE_VARIANTS => __( 'Export variants separately', 'storeaccountant' ),
		];
	}

	/**
	 * Sanitizes a requested variant export mode.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param mixed $mode Requested mode.
	 */
	public static function sanitize_mode( mixed $mode ): string {
		$mode = is_scalar( $mode ) ? sanitize_key( (string) $mode ) : '';

		return self::MODE_SEPARATE_VARIANTS === $mode ? self::MODE_SEPARATE_VARIANTS : self::MODE_PARENT_PRODUCTS;
	}
}
