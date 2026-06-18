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

use Throwable;
use WC_Order;
use WP_Post;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Diagnostic\DiagnosticIncident;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\AmountMutator;
use StoreAccountant\Export\Field\Mutator\DateMutator;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldProvider;
use StoreAccountant\Order\Export\Query\OrderQuery;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function is_array;
use function is_scalar;
use function array_key_exists;
use function sprintf;
use function str_ends_with;
use function str_starts_with;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the field mapping tab for WooCommerce order export configurations.
 */
final readonly class OrderFieldMappingTabProvider implements ExportConfigurationTabProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'order_field_mapping';
	public const TAB_ID      = 'field_mapping';

	/**
	 * Initializes the tab provider.
	 *
	 * @param ExportFieldResolver             $field_resolver Field resolver.
	 * @param FieldMappingRepository          $mapping          Field mapping repository.
	 * @param OrderTaxFieldProviderRegistry   $tax_field_providers Tax field provider registry.
	 * @param OrderTaxRateResolver            $tax_rates           Tax rate resolver.
	 * @param OrderQuery                      $order_query      Order query service.
	 * @param ExportFilterSelectionSerializer $filter_serializer Filter selection serializer.
	 * @param ExportFilterSnapshotter         $filter_snapshotter Filter snapshotter.
	 * @param PermissionChecker               $permissions     Permission checker.
	 * @param DiagnosticIncidentLogger        $diagnostics     Diagnostic incident logger.
	 */
	public function __construct(
		private ExportFieldResolver $field_resolver,
		private FieldMappingRepository $mapping,
		private OrderTaxFieldProviderRegistry $tax_field_providers,
		private OrderTaxRateResolver $tax_rates,
		private OrderQuery $order_query,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportFilterSnapshotter $filter_snapshotter,
		private PermissionChecker $permissions,
		private DiagnosticIncidentLogger $diagnostics
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		add_filter(
			'storeaccountant_export_configuration_tab_provider',
			function ( array $providers ): array {
				$providers[ self::PROVIDER_ID ] = $this;

				return $providers;
			},
			HookRegistrarInterface::DEFAULT_PRIORITY
		);

		add_action( 'admin_post_storeaccountant_save_order_field_mapping', [ $this, 'handle_save' ] );
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
	public function supports( WP_Post $configuration ): bool {
		$export_adapter = (string) get_post_meta( $configuration->ID, ExportConfigurationPostType::META_EXPORT_ADAPTER, true );

		return '' === $export_adapter || OrderExportAdapter::ADAPTER_ID === $export_adapter;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_tabs( WP_Post $configuration ): array {
		return [
			self::TAB_ID => __( 'Field Mapping', 'storeaccountant' ),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function render( string $tab, WP_Post $configuration, bool $read_only = false ): void {
		if ( self::TAB_ID !== $tab ) {
			return;
		}

		$context = $this->get_context( $configuration->ID );
		$fields  = $this->get_fields( $configuration->ID, $context );
		$items   = $this->mapping->get_form_items( $configuration->ID, $fields );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="storeaccountant_save_order_field_mapping" />
			<input type="hidden" name="storeaccountant_export_configuration_id" value="<?php echo esc_attr( (string) $configuration->ID ); ?>" />
			<?php wp_nonce_field( 'storeaccountant_save_order_field_mapping', 'storeaccountant_field_mapping_nonce' ); ?>

			<table class="widefat striped storeaccountant-field-mapping-table">
				<thead>
					<tr>
						<?php if ( ! $read_only ) : ?>
						<th scope="col" class="storeaccountant-field-mapping-sort-column">
							<span class="screen-reader-text"><?php esc_html_e( 'Sort', 'storeaccountant' ); ?></span>
						</th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'Enabled', 'storeaccountant' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Field', 'storeaccountant' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Description', 'storeaccountant' ); ?></th>
						<th scope="col" class="storeaccountant-field-mapping-label-column"><?php esc_html_e( 'Label', 'storeaccountant' ); ?></th>
						<th scope="col" class="storeaccountant-field-mapping-options-column"><?php esc_html_e( 'Options', 'storeaccountant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php $field = $fields->all()[ $item['field_id'] ] ?? null; ?>
						<?php if ( null === $field ) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<tr>
							<?php if ( ! $read_only ) : ?>
							<td class="storeaccountant-field-mapping-sort-column">
								<span class="storeaccountant-field-mapping-handle" aria-hidden="true">
									<span class="dashicons dashicons-menu" aria-hidden="true"></span>
								</span>
								<span class="screen-reader-text"><?php esc_html_e( 'Drag to sort this field', 'storeaccountant' ); ?></span>
								<input
									type="hidden"
									name="storeaccountant_field_mapping_order[]"
									value="<?php echo esc_attr( $item['field_id'] ); ?>"
									<?php disabled( $read_only ); ?>
								/>
							</td>
							<?php endif; ?>
							<td>
								<label>
									<input
										type="checkbox"
										name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][enabled]"
										value="1"
										<?php checked( $item['enabled'] ); ?>
										<?php disabled( $read_only ); ?>
									/>
									<span class="screen-reader-text"><?php esc_html_e( 'Export this field', 'storeaccountant' ); ?></span>
								</label>
							</td>
							<td><code><?php echo esc_html( $item['field_id'] ); ?></code></td>
							<td><?php echo esc_html( $this->get_field_description( $field ) ); ?></td>
							<td class="storeaccountant-field-mapping-label-column">
								<input
									type="text"
									name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][label]"
									value="<?php echo esc_attr( $item['label'] ); ?>"
									class="regular-text"
									<?php disabled( $read_only ); ?>
								/>
							</td>
							<td class="storeaccountant-field-mapping-options-column">
								<?php if ( $read_only ) : ?>
									<?php $this->render_read_only_options( $field, $item ); ?>
								<?php elseif ( $this->is_amount_field( $field ) ) : ?>
									<select name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][options][<?php echo esc_attr( AmountMutator::OPTION_AMOUNT_FORMAT ); ?>]">
										<option
											value="<?php echo esc_attr( AmountMutator::FORMAT_AMOUNT ); ?>"
											<?php selected( $this->get_amount_format_option( $item ), AmountMutator::FORMAT_AMOUNT ); ?>
										>
											<?php esc_html_e( 'Full amount', 'storeaccountant' ); ?>
										</option>
										<option
											value="<?php echo esc_attr( AmountMutator::FORMAT_CENTS ); ?>"
											<?php selected( $this->get_amount_format_option( $item ), AmountMutator::FORMAT_CENTS ); ?>
										>
											<?php esc_html_e( 'Cents', 'storeaccountant' ); ?>
										</option>
									</select>
								<?php endif; ?>
								<?php if ( ! $read_only && $this->is_date_field( $field ) ) : ?>
									<select name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][options][<?php echo esc_attr( DateMutator::OPTION_DATE_FORMAT ); ?>]">
										<?php foreach ( DateMutator::get_format_labels() as $format => $label ) : ?>
											<option
												value="<?php echo esc_attr( $format ); ?>"
												<?php selected( $this->get_date_format_option( $item ), $format ); ?>
											>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! $read_only ) : ?>
				<?php submit_button( __( 'Save Field Mapping', 'storeaccountant' ), 'primary', 'storeaccountant_save_field_mapping', false ); ?>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Handles field mapping submissions.
	 */
	public function handle_save(): void {
		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING ) ) {
			wp_die( esc_html__( 'You are not allowed to save field mappings.', 'storeaccountant' ) );
		}

		check_admin_referer( 'storeaccountant_save_order_field_mapping', 'storeaccountant_field_mapping_nonce' );

		$request          = Request::post_data();
		$configuration_id = Request::post_int( 'storeaccountant_export_configuration_id' );
		$configuration    = $configuration_id > 0 ? get_post( $configuration_id ) : null;

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type || ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING, $configuration_id ) || ! $this->supports( $configuration ) ) {
			$incident = $this->log_save_error( 'invalid_or_unauthorized_configuration', $configuration_id );
			$this->redirect_with_error( $configuration_id, $incident );
		}

		try {
			$fields  = $this->get_fields( $configuration_id, $this->get_context( $configuration_id ) );
			$mapping = $this->mapping->sanitize_from_request( $request, $fields );

			$this->mapping->save( $configuration_id, $mapping );
		} catch ( Throwable $exception ) {
			$incident = $this->log_save_error( 'field_mapping_persistence_failed', $configuration_id, $exception );
			$this->redirect_with_error( $configuration_id, $incident );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'                                => 'storeaccountant-export-configuration',
					'configuration_id'                    => (string) $configuration_id,
					'tab'                                 => self::TAB_ID,
					'storeaccountant_field_mapping_saved' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Rewrites only tax field mapping for the currently selected tax field provider.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 */
	public function refresh_tax_field_mapping( int $configuration_id ): void {
		$context = $this->get_context( $configuration_id );
		$fields  = $this->get_fields( $configuration_id, $context );

		$this->mapping->refresh_matching_fields(
			$configuration_id,
			$fields,
			static fn ( string $field_id ): bool => self::is_tax_field_id( $field_id )
		);
	}

	/**
	 * Redirects back to the field mapping tab with an error notice.
	 *
	 * @param int $configuration_id Export configuration post ID.
	 * @param DiagnosticIncident|null $incident Optional diagnostic incident.
	 */
	private function redirect_with_error( int $configuration_id, ?DiagnosticIncident $incident = null ): void {
		$args = [
			'page'                                => 'storeaccountant-export-configuration',
			'configuration_id'                    => (string) $configuration_id,
			'tab'                                 => self::TAB_ID,
			'storeaccountant_field_mapping_error' => '1',
		];

		if ( null !== $incident ) {
			$args['storeaccountant_diagnostic_support_id'] = $incident->support_id;
		}

		wp_safe_redirect(
			add_query_arg(
				$args,
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Logs a diagnostic incident for order field mapping save failures.
	 */
	private function log_save_error( string $reason, int $configuration_id, ?Throwable $throwable = null ): ?DiagnosticIncident {
		return $this->diagnostics->error(
			'order_field_mapping',
			__( 'The field mapping could not be saved.', 'storeaccountant' ),
			[
				'reason'           => $reason,
				'configuration_id' => $configuration_id,
			],
			null,
			$throwable
		);
	}

	/**
	 * Gets fields for the selected order tax field provider.
	 *
	 * @param int           $configuration_id Configuration post ID.
	 * @param ExportContext $context          Field context.
	 */
	private function get_fields( int $configuration_id, ExportContext $context ): FieldCollection {
		return $this->field_resolver->get_fields( $context );
	}

	/**
	 * Gets the selected amount format option for a mapping item.
	 *
	 * @param array<string, mixed> $item Mapping item.
	 */
	private function get_amount_format_option( array $item ): string {
		$options = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : [];
		$format  = $options[ AmountMutator::OPTION_AMOUNT_FORMAT ] ?? AmountMutator::FORMAT_AMOUNT;

		return AmountMutator::FORMAT_CENTS === $format ? AmountMutator::FORMAT_CENTS : AmountMutator::FORMAT_AMOUNT;
	}

	/**
	 * Gets the selected date format option for a mapping item.
	 *
	 * @param array<string, mixed> $item Mapping item.
	 */
	private function get_date_format_option( array $item ): string {
		$options = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : [];

		return DateMutator::sanitize_format( $options[ DateMutator::OPTION_DATE_FORMAT ] ?? DateMutator::FORMAT_ORIGINAL );
	}

	/**
	 * Checks whether a field is handled by the date mutator.
	 *
	 * @param Field $field Export field.
	 */
	private function is_date_field( Field $field ): bool {
		return $field->type instanceof DateTimeFieldType;
	}

	/**
	 * Checks whether a field is handled by the amount mutator.
	 *
	 * @param Field $field Export field.
	 */
	private function is_amount_field( Field $field ): bool {
		return $field->type instanceof NumberFieldType && $field->type->is_decimal();
	}

	/**
	 * Renders configured mutator options without editable controls.
	 *
	 * @param Field                $field Export field.
	 * @param array<string, mixed> $item  Mapping item.
	 */
	private function render_read_only_options( Field $field, array $item ): void {
		$options = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : [];

		if ( $this->is_amount_field( $field ) && array_key_exists( AmountMutator::OPTION_AMOUNT_FORMAT, $options ) ) {
			$value = $this->get_amount_format_option( $item );
			echo esc_html( AmountMutator::FORMAT_CENTS === $value ? __( 'Cents', 'storeaccountant' ) : __( 'Full amount', 'storeaccountant' ) );
			return;
		}

		if ( $this->is_date_field( $field ) && array_key_exists( DateMutator::OPTION_DATE_FORMAT, $options ) ) {
			$labels = DateMutator::get_format_labels();
			$value  = $this->get_date_format_option( $item );
			echo esc_html( $labels[ $value ] ?? $value );
		}
	}

	/**
	 * Gets a translated field description for the mapping UI.
	 *
	 * @param Field $field Export field.
	 */
	private function get_field_description( Field $field ): string {
		if ( $this->is_order_meta_field( $field ) ) {
			return sprintf(
				/* translators: %s: WooCommerce order meta key. */
				__( 'Custom field: %s', 'storeaccountant' ),
				(string) $field->options[ OrderMetaFieldProvider::OPTION_META_KEY ]
			);
		}

		return match ( $field->id ) {
			'order_id'             => __( 'order_id', 'storeaccountant' ),
			'order_number'         => __( 'order_number', 'storeaccountant' ),
			'invoice_number'       => __( 'invoice_number', 'storeaccountant' ),
			'invoice_date'         => __( 'invoice_date', 'storeaccountant' ),
			'invoice_file_name'    => __( 'invoice_file_name', 'storeaccountant' ),
			'order_date'           => __( 'order_date', 'storeaccountant' ),
			'order_status'         => __( 'order_status', 'storeaccountant' ),
			'currency'             => __( 'currency', 'storeaccountant' ),
			'payment_method'       => __( 'payment_method', 'storeaccountant' ),
			'payment_method_title' => __( 'payment_method_title', 'storeaccountant' ),
			'customer_id'          => __( 'customer_id', 'storeaccountant' ),
			'billing_first_name'   => __( 'billing_first_name', 'storeaccountant' ),
			'billing_last_name'    => __( 'billing_last_name', 'storeaccountant' ),
			'billing_company'      => __( 'billing_company', 'storeaccountant' ),
			'billing_street'       => __( 'billing_street', 'storeaccountant' ),
			'billing_house_number' => __( 'billing_house_number', 'storeaccountant' ),
			'billing_address_1'    => __( 'billing_address_1', 'storeaccountant' ),
			'billing_address_2'    => __( 'billing_address_2', 'storeaccountant' ),
			'billing_postcode'     => __( 'billing_postcode', 'storeaccountant' ),
			'billing_city'         => __( 'billing_city', 'storeaccountant' ),
			'billing_state'        => __( 'billing_state', 'storeaccountant' ),
			'billing_country'      => __( 'billing_country', 'storeaccountant' ),
			'billing_email'        => __( 'billing_email', 'storeaccountant' ),
			'billing_phone'        => __( 'billing_phone', 'storeaccountant' ),
			'shipping_first_name'   => __( 'shipping_first_name', 'storeaccountant' ),
			'shipping_last_name'    => __( 'shipping_last_name', 'storeaccountant' ),
			'shipping_company'      => __( 'shipping_company', 'storeaccountant' ),
			'shipping_street'       => __( 'shipping_street', 'storeaccountant' ),
			'shipping_house_number' => __( 'shipping_house_number', 'storeaccountant' ),
			'shipping_address_1'    => __( 'shipping_address_1', 'storeaccountant' ),
			'shipping_address_2'    => __( 'shipping_address_2', 'storeaccountant' ),
			'shipping_postcode'     => __( 'shipping_postcode', 'storeaccountant' ),
			'shipping_city'         => __( 'shipping_city', 'storeaccountant' ),
			'shipping_state'        => __( 'shipping_state', 'storeaccountant' ),
			'shipping_country'      => __( 'shipping_country', 'storeaccountant' ),
			'shipping_phone'        => __( 'shipping_phone', 'storeaccountant' ),
			'order_subtotal'       => __( 'order_subtotal', 'storeaccountant' ),
			'discount_total'       => __( 'discount_total', 'storeaccountant' ),
			'shipping_total'       => __( 'shipping_total', 'storeaccountant' ),
			'fee_total'            => __( 'fee_total', 'storeaccountant' ),
			'order_total'          => __( 'order_total', 'storeaccountant' ),
			'tax_items_total'      => __( 'tax_items_total', 'storeaccountant' ),
			'tax_shipping_total'   => __( 'tax_shipping_total', 'storeaccountant' ),
			default                => $this->get_tax_field_description( $field->id ),
		};
	}

	/**
	 * Checks whether a field represents WooCommerce order meta.
	 *
	 * @param Field $field Export field.
	 */
	private function is_order_meta_field( Field $field ): bool {
		return str_starts_with( $field->id, OrderMetaFieldProvider::FIELD_ID_PREFIX )
			&& isset( $field->options[ OrderMetaFieldProvider::OPTION_META_KEY ] )
			&& is_scalar( $field->options[ OrderMetaFieldProvider::OPTION_META_KEY ] );
	}

	/**
	 * Gets a translated description for dynamic tax rate fields.
	 *
	 * @param string $field_id Field identifier.
	 */
	private function get_tax_field_description( string $field_id ): string {
		if ( ! str_starts_with( $field_id, 'tax_' ) ) {
			return '';
		}

		if ( str_ends_with( $field_id, '_items' ) ) {
			return __( 'tax_rate_items_total', 'storeaccountant' );
		}

		if ( str_ends_with( $field_id, '_shipping' ) ) {
			return __( 'tax_rate_shipping_total', 'storeaccountant' );
		}

		if ( str_ends_with( $field_id, '_total' ) ) {
			return __( 'tax_rate_total', 'storeaccountant' );
		}

		return '';
	}

	/**
	 * Gets the field context for the mapping UI.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_context( int $configuration_id ): ExportContext {
		$orders = $this->get_configuration_orders( $configuration_id );

		return new ExportContext(
			OrderExportAdapter::ADAPTER_ID,
			$configuration_id,
			$orders,
			[
				'tax_rates'             => $this->tax_rates->get_tax_rates( $orders ),
				'tax_field_provider_id' => $this->get_tax_field_provider_id( $configuration_id ),
			]
		);
	}

	/**
	 * Gets orders covered by the current configuration.
	 *
	 * @param int $configuration_id Configuration post ID.
	 *
	 * @return array<int, WC_Order>
	 */
	private function get_configuration_orders( int $configuration_id ): array {
		$filters = $this->filter_serializer->decode( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_FILTERS, true ) );
		$filters = $this->filter_snapshotter->snapshot( $filters );

		if ( is_wp_error( $filters ) ) {
			return [];
		}

		$orders = $this->order_query->get_orders(
			new ExportPayload(
				0,
				OrderExportAdapter::ADAPTER_ID,
				$filters,
				[
					'configuration_id' => $configuration_id,
				]
			)
		);

		return is_array( $orders ) ? $orders : [];
	}

	/**
	 * Gets the selected tax field provider ID.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_tax_field_provider_id( int $configuration_id ): string {
		$provider_id = (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_ORDER_TAX_FIELD_PROVIDER, true );

		if ( '' !== $provider_id && null !== $this->tax_field_providers->get_provider( $provider_id ) ) {
			return $provider_id;
		}

		return ExtendedOrderTaxFieldProvider::PROVIDER_ID;
	}

	/**
	 * Checks whether a field ID belongs to order tax field providers.
	 *
	 * @param string $field_id Field identifier.
	 */
	private static function is_tax_field_id( string $field_id ): bool {
		return 'tax_items_total' === $field_id || 'tax_shipping_total' === $field_id || str_starts_with( $field_id, 'tax_' );
	}
}
