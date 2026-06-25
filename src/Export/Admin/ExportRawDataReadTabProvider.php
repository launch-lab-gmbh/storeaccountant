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

namespace StoreAccountant\Export\Admin;

use WP_Post;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Export\Contract\ExportReadTabProviderInterface;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use function is_bool;
use function is_scalar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the raw configuration data tab for saved exports.
 */
final readonly class ExportRawDataReadTabProvider implements ExportReadTabProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'export_raw_data';
	public const TAB_ID      = 'raw-data';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private ExportFilterSelectionSerializer $filter_serializer
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_read_tab_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
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
		return self::PROVIDER_ID;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function supports( WP_Post $export ): bool {
		return ExportPostType::POST_TYPE === $export->post_type;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_tabs( WP_Post $export ): array {
		return [
			self::TAB_ID => __( 'Raw Data', 'storeaccountant' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( string $tab, WP_Post $export ): void {
		if ( self::TAB_ID !== $tab ) {
			return;
		}
		?>
		<table class="widefat striped storeaccountant-export-raw-data">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Field', 'storeaccountant' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Value', 'storeaccountant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->get_configured_raw_data( $export ) as $field => $value ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $field ); ?></td>
						<td><pre><?php echo esc_html( $this->format_raw_value( $value ) ); ?></pre></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Gets export-related raw configuration data for developer inspection.
	 *
	 * @param WP_Post $export Current export.
	 *
	 * @return array<string, mixed>
	 */
	private function get_configured_raw_data( WP_Post $export ): array {
		$data = [
			'export_title'     => get_the_title( $export ),
			'export_adapter'   => (string) get_post_meta( $export->ID, ExportPostType::META_EXPORT_ADAPTER, true ),
			'export_writer'    => (string) get_post_meta( $export->ID, ExportPostType::META_EXPORT_WRITER, true ),
			'storage_engine'   => (string) get_post_meta( $export->ID, ExportPostType::META_STORAGE_ENGINE, true ),
			'configuration_id' => (int) get_post_meta( $export->ID, ExportPostType::META_CONFIGURATION_ID, true ),
		];

		foreach ( $this->filter_serializer->decode( (string) get_post_meta( $export->ID, ExportPostType::META_FILTERS, true ) ) as $filter ) {
			$data[ 'filter.' . $filter->filter_id ] = $filter->settings;
		}

		return $data;
	}

	/**
	 * Formats a raw configuration value for display.
	 *
	 * @param mixed $value Raw value.
	 */
	private function format_raw_value( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_scalar( $value ) || null === $value ) {
			return (string) $value;
		}

		return (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
