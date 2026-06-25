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
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function is_array;
use function is_scalar;
use function is_string;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the technical export log tab for saved exports.
 */
final readonly class ExportLogReadTabProvider implements ExportReadTabProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'export_log';
	public const TAB_ID      = 'log';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private PermissionChecker $permissions
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
		return ExportPostType::POST_TYPE === $export->post_type
			&& $this->permissions->can( PermissionActionIds::EXPORT_VIEW_LOG, $export->ID );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_tabs( WP_Post $export ): array {
		return [
			self::TAB_ID => __( 'Log', 'storeaccountant' ),
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

		$entries = get_post_meta( $export->ID, ExportPostType::META_LOG_ENTRIES, true );
		$entries = is_array( $entries ) ? $entries : [];
		?>
		<?php if ( [] === $entries ) : ?>
			<p><?php esc_html_e( 'No export log entries are available.', 'storeaccountant' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<table class="widefat striped storeaccountant-export-log">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Time', 'storeaccountant' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Level', 'storeaccountant' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Message', 'storeaccountant' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Details', 'storeaccountant' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( array_reverse( $entries ) as $entry ) : ?>
					<?php $entry = is_array( $entry ) ? $entry : []; ?>
					<tr>
						<td><?php echo esc_html( $this->format_value( $entry['time'] ?? '' ) ); ?></td>
						<td>
							<?php $level = $this->format_value( $entry['level'] ?? '' ); ?>
							<span class="storeaccountant-log-level storeaccountant-log-level--<?php echo esc_attr( sanitize_html_class( $level ) ); ?>">
								<?php echo esc_html( $this->format_level_label( $level ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $this->format_value( $entry['message'] ?? '' ) ); ?></td>
						<td class="storeaccountant-export-log-details">
							<div class="storeaccountant-export-log-details-scroll">
								<pre><?php echo esc_html( $this->format_details( $entry ) ); ?></pre>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Formats one scalar value.
	 *
	 * @param mixed $value Raw value.
	 */
	private function format_value( mixed $value ): string {
		return is_scalar( $value ) || null === $value ? (string) $value : $this->format_details( $value );
	}

	/**
	 * Formats one log level label.
	 *
	 * @param string $level Raw log level.
	 */
	private function format_level_label( string $level ): string {
		return match ( $level ) {
			'error' => __( 'Error', 'storeaccountant' ),
			'success' => __( 'Success', 'storeaccountant' ),
			default => __( 'Info', 'storeaccountant' ),
		};
	}

	/**
	 * Formats entry details for display.
	 *
	 * @param mixed $value Raw value.
	 */
	private function format_details( mixed $value ): string {
		$encoded = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return is_string( $encoded ) ? $encoded : '';
	}
}
