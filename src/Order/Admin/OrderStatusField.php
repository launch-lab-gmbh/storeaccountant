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

namespace StoreAccountant\Order\Admin;

use StoreAccountant\Order\Export\OrderStatusProvider;
use function array_map;
use function array_values;
use function in_array;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes the order status export filter field.
 */
final readonly class OrderStatusField {
	public const FIELD_NAME = 'storeaccountant_order_statuses';

	public function __construct(
		private OrderStatusProvider $order_statuses
	) {}

	/**
	 * Renders the order status checkbox field.
	 *
	 * @param array<int, string> $selected_statuses Selected order status keys.
	 */
	public function render( array $selected_statuses = [], bool $read_only = false ): void {
		$statuses          = $this->order_statuses->get_statuses();
		$selected_statuses = [] !== $selected_statuses ? $selected_statuses : $this->order_statuses->get_default_statuses();
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Order Statuses', 'storeaccountant' ); ?></th>
			<td>
				<?php if ( [] === $statuses ) : ?>
					<p class="description"><?php esc_html_e( 'No WooCommerce order statuses are available.', 'storeaccountant' ); ?></p>
				<?php elseif ( $read_only ) : ?>
					<?php $selected_labels = $this->get_selected_status_labels( $statuses, $selected_statuses ); ?>
					<?php if ( [] === $selected_labels ) : ?>
						<?php esc_html_e( 'Not set', 'storeaccountant' ); ?>
					<?php else : ?>
						<ul>
							<?php foreach ( $selected_labels as $label ) : ?>
								<li><?php echo esc_html( $label ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				<?php else : ?>
					<div
						class="storeaccountant-order-status-token-field"
						data-field-name="<?php echo esc_attr( self::FIELD_NAME ); ?>"
						data-label="<?php esc_attr_e( 'Order Statuses', 'storeaccountant' ); ?>"
						data-statuses="<?php echo esc_attr( (string) wp_json_encode( $this->get_status_options( $statuses ) ) ); ?>"
						data-selected-statuses="<?php echo esc_attr( (string) wp_json_encode( array_values( $selected_statuses ) ) ); ?>"
					></div>
					<fieldset class="storeaccountant-order-status-checkboxes">
						<legend class="screen-reader-text">
							<span><?php esc_html_e( 'Order Statuses', 'storeaccountant' ); ?></span>
						</legend>
						<?php foreach ( $statuses as $status => $label ) : ?>
							<?php $field_id = 'storeaccountant-order-status-' . sanitize_html_class( $status ); ?>
							<label for="<?php echo esc_attr( $field_id ); ?>">
								<input
									type="checkbox"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( self::FIELD_NAME ); ?>[]"
									value="<?php echo esc_attr( $status ); ?>"
									<?php checked( in_array( $status, $selected_statuses, true ) ); ?>
								/>
								<?php echo esc_html( $label ); ?>
							</label>
							<br />
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Only orders with at least one selected status will be included.', 'storeaccountant' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Gets order status options for the enhanced token field.
	 *
	 * @param array<string, string> $statuses Available order statuses.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function get_status_options( array $statuses ): array {
		return array_map(
			static fn ( string $status, string $label ): array => [
				'value' => $status,
				'label' => $label,
			],
			array_keys( $statuses ),
			$statuses
		);
	}

	/**
	 * Gets labels for selected order statuses.
	 *
	 * @param array<string, string> $statuses          Available order statuses.
	 * @param array<int, string>    $selected_statuses Selected order status keys.
	 *
	 * @return array<int, string>
	 */
	private function get_selected_status_labels( array $statuses, array $selected_statuses ): array {
		$labels = [];

		foreach ( $selected_statuses as $status ) {
			if ( isset( $statuses[ $status ] ) ) {
				$labels[] = $statuses[ $status ];
			}
		}

		return array_values( $labels );
	}

	/**
	 * Gets selected order statuses from request data.
	 *
	 * @param array<string, mixed> $request Request data.
	 *
	 * @return array<int, string>
	 */
	public function get_statuses_from_request( array $request ): array {
		return $this->order_statuses->sanitize_statuses( $request[ self::FIELD_NAME ] ?? [] );
	}

	/**
	 * Gets the default selected order statuses.
	 *
	 * @return array<int, string>
	 */
	public function get_default_statuses(): array {
		return $this->order_statuses->get_default_statuses();
	}
}
