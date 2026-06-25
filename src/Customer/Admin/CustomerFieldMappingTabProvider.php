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

namespace StoreAccountant\Customer\Admin;

use Throwable;
use WP_Post;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Diagnostic\DiagnosticIncident;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Query\CustomerQuery;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Contract\ExportConfigurationTabProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\AmountMutator;
use StoreAccountant\Export\Field\Mutator\DateMutator;
use StoreAccountant\Export\Field\Type\DateTimeFieldType;
use StoreAccountant\Export\Field\Type\NumberFieldType;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Customer\Export\Field\Provider\CustomerMetaFieldProvider;
use StoreAccountant\Security\Permission\PermissionActionIds;
use StoreAccountant\Security\Permission\PermissionChecker;
use function array_key_exists;
use function is_array;
use function is_scalar;
use function sprintf;
use function str_starts_with;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the field mapping tab for WooCommerce customer export configurations.
 */
final readonly class CustomerFieldMappingTabProvider implements ExportConfigurationTabProviderInterface, HookRegistrarInterface {
	public const PROVIDER_ID = 'customer_field_mapping';
	public const TAB_ID      = 'field_mapping';

	/**
	 * Initializes the tab provider.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param ExportFieldResolver             $field_resolver     Field resolver.
	 * @param FieldMappingRepository          $mapping            Field mapping repository.
	 * @param CustomerQuery                   $customer_query     Customer query service.
	 * @param ExportFilterSelectionSerializer $filter_serializer  Filter selection serializer.
	 * @param ExportFilterSnapshotter         $filter_snapshotter Filter snapshotter.
	 * @param PermissionChecker               $permissions        Permission checker.
	 * @param DiagnosticIncidentLogger        $diagnostics        Diagnostic incident logger.
	 */
	public function __construct(
		private ExportFieldResolver $field_resolver,
		private FieldMappingRepository $mapping,
		private CustomerQuery $customer_query,
		private ExportFilterSelectionSerializer $filter_serializer,
		private ExportFilterSnapshotter $filter_snapshotter,
		private PermissionChecker $permissions,
		private DiagnosticIncidentLogger $diagnostics
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
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

		add_action( 'admin_post_storeaccountant_save_customer_field_mapping', [ $this, 'handle_save' ] );
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
	public function supports( WP_Post $configuration ): bool {
		$export_adapter = (string) get_post_meta( $configuration->ID, ExportConfigurationPostType::META_EXPORT_ADAPTER, true );

		return CustomerExportAdapter::ADAPTER_ID === $export_adapter;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function get_tabs( WP_Post $configuration ): array {
		return [
			self::TAB_ID => __( 'Field Mapping', 'storeaccountant' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function render( string $tab, WP_Post $configuration, bool $read_only = false ): void {
		if ( self::TAB_ID !== $tab ) {
			return;
		}

		$context = $this->get_context( $configuration->ID );
		$fields  = $this->field_resolver->get_fields( $context );
		$items   = $this->mapping->get_form_items( $configuration->ID, $fields );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="storeaccountant_save_customer_field_mapping" />
			<input type="hidden" name="storeaccountant_export_configuration_id" value="<?php echo esc_attr( (string) $configuration->ID ); ?>" />
			<?php wp_nonce_field( 'storeaccountant_save_customer_field_mapping', 'storeaccountant_field_mapping_nonce' ); ?>

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
								<input type="hidden" name="storeaccountant_field_mapping_order[]" value="<?php echo esc_attr( $item['field_id'] ); ?>" <?php disabled( $read_only ); ?> />
							</td>
							<?php endif; ?>
							<td>
								<label>
									<input type="checkbox" name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][enabled]" value="1" <?php checked( $item['enabled'] ); ?> <?php disabled( $read_only ); ?> />
									<span class="screen-reader-text"><?php esc_html_e( 'Export this field', 'storeaccountant' ); ?></span>
								</label>
							</td>
							<td><code><?php echo esc_html( $item['field_id'] ); ?></code></td>
							<td><?php echo esc_html( $this->get_field_description( $field ) ); ?></td>
							<td class="storeaccountant-field-mapping-label-column">
								<input type="text" name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][label]" value="<?php echo esc_attr( $item['label'] ); ?>" class="regular-text" <?php disabled( $read_only ); ?> />
							</td>
							<td class="storeaccountant-field-mapping-options-column">
								<?php if ( $read_only ) : ?>
									<?php $this->render_read_only_options( $field, $item ); ?>
								<?php elseif ( $this->is_amount_field( $field ) ) : ?>
									<select name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][options][<?php echo esc_attr( AmountMutator::OPTION_AMOUNT_FORMAT ); ?>]">
										<option value="<?php echo esc_attr( AmountMutator::FORMAT_AMOUNT ); ?>" <?php selected( $this->get_amount_format_option( $item ), AmountMutator::FORMAT_AMOUNT ); ?>>
											<?php esc_html_e( 'Full amount', 'storeaccountant' ); ?>
										</option>
										<option value="<?php echo esc_attr( AmountMutator::FORMAT_CENTS ); ?>" <?php selected( $this->get_amount_format_option( $item ), AmountMutator::FORMAT_CENTS ); ?>>
											<?php esc_html_e( 'Cents', 'storeaccountant' ); ?>
										</option>
									</select>
								<?php endif; ?>
								<?php if ( ! $read_only && $this->is_date_field( $field ) ) : ?>
									<select name="storeaccountant_field_mapping[<?php echo esc_attr( $item['field_id'] ); ?>][options][<?php echo esc_attr( DateMutator::OPTION_DATE_FORMAT ); ?>]">
										<?php foreach ( DateMutator::get_format_labels() as $format => $label ) : ?>
											<option value="<?php echo esc_attr( $format ); ?>" <?php selected( $this->get_date_format_option( $item ), $format ); ?>>
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
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function handle_save(): void {
		if ( ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING ) ) {
			wp_die( esc_html__( 'You are not allowed to save field mappings.', 'storeaccountant' ) );
		}

		check_admin_referer( 'storeaccountant_save_customer_field_mapping', 'storeaccountant_field_mapping_nonce' );

		$request          = Request::post_data();
		$configuration_id = Request::post_int( 'storeaccountant_export_configuration_id' );
		$configuration    = $configuration_id > 0 ? get_post( $configuration_id ) : null;

		if ( ! $configuration || ExportConfigurationPostType::POST_TYPE !== $configuration->post_type || ! $this->permissions->can( PermissionActionIds::CONFIGURATION_EDIT_FIELD_MAPPING, $configuration_id ) || ! $this->supports( $configuration ) ) {
			$incident = $this->log_save_error( 'invalid_or_unauthorized_configuration', $configuration_id );
			$this->redirect_with_error( $configuration_id, $incident );
		}

		try {
			$fields  = $this->field_resolver->get_fields( $this->get_context( $configuration_id ) );
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
	 * Logs a diagnostic incident for customer field mapping save failures.
	 */
	private function log_save_error( string $reason, int $configuration_id, ?Throwable $throwable = null ): ?DiagnosticIncident {
		return $this->diagnostics->error(
			'customer_field_mapping',
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
		if ( $this->is_customer_meta_field( $field ) ) {
			return sprintf(
				/* translators: %s: WooCommerce customer meta key. */
				__( 'Custom field: %s', 'storeaccountant' ),
				(string) $field->options[ CustomerMetaFieldProvider::OPTION_META_KEY ]
			);
		}

		return match ( $field->id ) {
			'customer_id' => __( 'customer_id', 'storeaccountant' ),
			'username' => __( 'username', 'storeaccountant' ),
			'email' => __( 'email', 'storeaccountant' ),
			'first_name' => __( 'first_name', 'storeaccountant' ),
			'last_name' => __( 'last_name', 'storeaccountant' ),
			'display_name' => __( 'display_name', 'storeaccountant' ),
			'date_created' => __( 'date_created', 'storeaccountant' ),
			'date_modified' => __( 'date_modified', 'storeaccountant' ),
			'order_count' => __( 'order_count', 'storeaccountant' ),
			'total_spent' => __( 'total_spent', 'storeaccountant' ),
			'billing_first_name' => __( 'billing_first_name', 'storeaccountant' ),
			'billing_last_name' => __( 'billing_last_name', 'storeaccountant' ),
			'billing_company' => __( 'billing_company', 'storeaccountant' ),
			'billing_street' => __( 'billing_street', 'storeaccountant' ),
			'billing_house_number' => __( 'billing_house_number', 'storeaccountant' ),
			'billing_address_1' => __( 'billing_address_1', 'storeaccountant' ),
			'billing_address_2' => __( 'billing_address_2', 'storeaccountant' ),
			'billing_postcode' => __( 'billing_postcode', 'storeaccountant' ),
			'billing_city' => __( 'billing_city', 'storeaccountant' ),
			'billing_state' => __( 'billing_state', 'storeaccountant' ),
			'billing_country' => __( 'billing_country', 'storeaccountant' ),
			'billing_email' => __( 'billing_email', 'storeaccountant' ),
			'billing_phone' => __( 'billing_phone', 'storeaccountant' ),
			'shipping_first_name' => __( 'shipping_first_name', 'storeaccountant' ),
			'shipping_last_name' => __( 'shipping_last_name', 'storeaccountant' ),
			'shipping_company' => __( 'shipping_company', 'storeaccountant' ),
			'shipping_street' => __( 'shipping_street', 'storeaccountant' ),
			'shipping_house_number' => __( 'shipping_house_number', 'storeaccountant' ),
			'shipping_address_1' => __( 'shipping_address_1', 'storeaccountant' ),
			'shipping_address_2' => __( 'shipping_address_2', 'storeaccountant' ),
			'shipping_postcode' => __( 'shipping_postcode', 'storeaccountant' ),
			'shipping_city' => __( 'shipping_city', 'storeaccountant' ),
			'shipping_state' => __( 'shipping_state', 'storeaccountant' ),
			'shipping_country' => __( 'shipping_country', 'storeaccountant' ),
			'shipping_phone' => __( 'shipping_phone', 'storeaccountant' ),
			default => '',
		};
	}

	/**
	 * Checks whether a field represents WooCommerce customer meta.
	 *
	 * @param Field $field Export field.
	 */
	private function is_customer_meta_field( Field $field ): bool {
		return str_starts_with( $field->id, CustomerMetaFieldProvider::FIELD_ID_PREFIX )
			&& isset( $field->options[ CustomerMetaFieldProvider::OPTION_META_KEY ] )
			&& is_scalar( $field->options[ CustomerMetaFieldProvider::OPTION_META_KEY ] );
	}

	/**
	 * Gets the field context for the mapping UI.
	 *
	 * @param int $configuration_id Configuration post ID.
	 */
	private function get_context( int $configuration_id ): ExportContext {
		$customers = $this->get_configuration_customers( $configuration_id );

		return new ExportContext(
			CustomerExportAdapter::ADAPTER_ID,
			$configuration_id,
			$customers
		);
	}

	/**
	 * Gets customers covered by the current configuration.
	 *
	 * @param int $configuration_id Configuration post ID.
	 *
	 * @return array<int, mixed>
	 */
	private function get_configuration_customers( int $configuration_id ): array {
		$filters = $this->filter_serializer->decode( (string) get_post_meta( $configuration_id, ExportConfigurationPostType::META_FILTERS, true ) );
		$filters = $this->filter_snapshotter->snapshot( $filters );

		if ( is_wp_error( $filters ) ) {
			return [];
		}

		$customers = $this->customer_query->get_customers(
			new ExportPayload(
				0,
				CustomerExportAdapter::ADAPTER_ID,
				$filters,
				[
					'configuration_id' => $configuration_id,
				]
			)
		);

		return is_array( $customers ) ? $customers : [];
	}
}
