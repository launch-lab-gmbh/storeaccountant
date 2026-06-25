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

namespace StoreAccountant\Queue\Admin;

use StoreAccountant\Queue\QueueTransportRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders queue transport settings.
 */
final readonly class QueueTransportsSettingsForm {
	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private QueueTransportRegistry $queue_transports
	) {}

	/**
	 * Renders the queue transport fields.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render_fields(): void {
		$active_provider = $this->queue_transports->get_active();
		$active_id       = null !== $active_provider ? $active_provider->get_id() : '';
		?>
		<?php foreach ( $this->queue_transports->get_all() as $provider ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $provider->get_label() ); ?></th>
				<td>
					<label for="storeaccountant-queue-transport-<?php echo esc_attr( $provider->get_id() ); ?>">
						<input
							type="radio"
							id="storeaccountant-queue-transport-<?php echo esc_attr( $provider->get_id() ); ?>"
							name="storeaccountant_queue_transport_provider"
							value="<?php echo esc_attr( $provider->get_id() ); ?>"
							<?php checked( $active_id, $provider->get_id() ); ?>
						/>
						<?php esc_html_e( 'Use this queue transport.', 'storeaccountant' ); ?>
					</label>
					<p class="description"><?php echo esc_html( $provider->get_description() ); ?></p>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( [] === $this->queue_transports->get_all() ) : ?>
			<tr>
				<td colspan="2"><?php esc_html_e( 'No queue transports are registered.', 'storeaccountant' ); ?></td>
			</tr>
		<?php endif; ?>
		<?php
	}

	/**
	 * Saves the selected queue transport from request data.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<string, mixed> $request Request data.
	 */
	public function save_from_request( array $request ): void {
		$provider_id = isset( $request['storeaccountant_queue_transport_provider'] )
			? sanitize_key( (string) wp_unslash( $request['storeaccountant_queue_transport_provider'] ) )
			: '';

		if ( '' !== $provider_id ) {
			$this->queue_transports->save_active( $provider_id );
		}
	}
}
