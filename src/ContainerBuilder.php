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

namespace StoreAccountant;

use League\Container\Container;
use League\Container\Definition\DefinitionAggregate;
use League\Container\Inflector\InflectorAggregate;
use League\Container\ServiceProvider\ServiceProviderAggregate;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Serialization\Serializer as MessengerTransportSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface as MessengerTransportSerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Admin\AdminAssets;
use StoreAccountant\Admin\AccountingOverviewTabProviderRegistry;
use StoreAccountant\Export\Admin\AccountingExportPage;
use StoreAccountant\Export\Admin\AccountingExportPageForm;
use StoreAccountant\Export\Admin\ExportSettingsFields;
use StoreAccountant\Admin\AccountingHeaderBar;
use StoreAccountant\Admin\AccountingMenu;
use StoreAccountant\Admin\AccountingSupportAccess;
use StoreAccountant\Admin\AccountingSupportPage;
use StoreAccountant\Admin\ExportConfigurationOverviewTabProvider;
use StoreAccountant\Admin\ExportOverviewTabProvider;
use StoreAccountant\Admin\SupportOverviewTabProvider;
use StoreAccountant\Customer\Admin\CustomerCountryFilterFieldProvider;
use StoreAccountant\Customer\Admin\CustomerDateFilterFieldProvider;
use StoreAccountant\Customer\Admin\CustomerFieldMappingTabProvider;
use StoreAccountant\Diagnostic\Admin\DiagnosticIncidentDownloadController;
use StoreAccountant\Diagnostic\Admin\DiagnosticSettingsTabProvider;
use StoreAccountant\Diagnostic\DiagnosticIncidentLogger;
use StoreAccountant\Diagnostic\DiagnosticIncidentRepository;
use StoreAccountant\Diagnostic\DiagnosticLogConfiguration;
use StoreAccountant\Diagnostic\DiagnosticSettings;
use StoreAccountant\Export\Configuration\Admin\ExportConfigurationPage;
use StoreAccountant\Export\Configuration\Admin\ExportConfigurationPageForm;
use StoreAccountant\Export\Admin\ExportDetailsReadTabProvider;
use StoreAccountant\Export\Admin\ExportLogReadTabProvider;
use StoreAccountant\Export\Admin\ExportRawDataReadTabProvider;
use StoreAccountant\Export\Admin\Period\MonthYearExportPeriodFieldProvider;
use StoreAccountant\Invoice\Admin\InvoicePluginForm;
use StoreAccountant\Order\Admin\OrderDateFilterFieldProvider;
use StoreAccountant\Order\Admin\OrderFieldMappingTabProvider;
use StoreAccountant\Order\Admin\OrderStatusFilterFieldProvider;
use StoreAccountant\Order\Admin\OrderStatusField;
use StoreAccountant\Product\Admin\ProductDateFilterFieldProvider;
use StoreAccountant\Product\Admin\ProductFieldMappingTabProvider;
use StoreAccountant\Product\Admin\ProductVariantExportFieldProvider;
use StoreAccountant\Security\Admin\PermissionsSettingsForm;
use StoreAccountant\Settings\Admin\PluginSettingsPage;
use StoreAccountant\Settings\Admin\PluginSettingsTabProviderRegistry;
use StoreAccountant\Queue\Admin\QueueTransportsSettingsForm;
use StoreAccountant\Security\Admin\SecuritySettingsForm;
use StoreAccountant\Storage\Admin\StorageActivationNotice;
use StoreAccountant\Storage\Admin\StorageLocationsForm;
use StoreAccountant\Event\EventSubscriberRegistrar;
use StoreAccountant\Export\Configuration\ExportConfigurationFormFieldProviderRegistry;
use StoreAccountant\Export\Configuration\ExportConfigurationPostType;
use StoreAccountant\Export\Configuration\ExportConfigurationRepository;
use StoreAccountant\Export\Configuration\ExportConfigurationTabProviderRegistry;
use StoreAccountant\Export\Event\ExportEventLogSubscriber;
use StoreAccountant\Customer\Export\Adapter\CustomerExportAdapter;
use StoreAccountant\Customer\Export\Field\Provider\CustomerFieldProvider;
use StoreAccountant\Customer\Export\Field\Provider\CustomerFieldValueProvider;
use StoreAccountant\Customer\Export\Field\Provider\CustomerMetaFieldProvider;
use StoreAccountant\Customer\Export\Field\Provider\CustomerMetaFieldValueProvider;
use StoreAccountant\Customer\Export\Filter\CustomerCountryFilter;
use StoreAccountant\Customer\Export\Filter\CustomerDateFilter;
use StoreAccountant\Customer\Export\Query\CustomerQuery;
use StoreAccountant\Order\Export\Adapter\OrderExportAdapter;
use StoreAccountant\Product\Export\Adapter\ProductExportAdapter;
use StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry;
use StoreAccountant\Export\Admin\ExportListPollingAjaxController;
use StoreAccountant\Export\Admin\ExportListPollingResponseFactory;
use StoreAccountant\Export\Download\DownloadPasswordManager;
use StoreAccountant\Export\Download\ExportDownloadController;
use StoreAccountant\Export\Download\ExportDownloadUrlFactory;
use StoreAccountant\Export\Download\StorageFileStreamer;
use StoreAccountant\Export\Exporter;
use StoreAccountant\Export\ExportAdapterRegistry;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportRendererRegistry;
use StoreAccountant\Export\ExportReadTabProviderRegistry;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\Filter\ExportFilterFieldProviderRegistry;
use StoreAccountant\Export\Filter\ExportFilterRegistry;
use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
use StoreAccountant\Export\Filter\ExportFilterSnapshotter;
use StoreAccountant\Order\Export\Filter\OrderDateFilter;
use StoreAccountant\Order\Export\Filter\OrderStatusFilter;
use StoreAccountant\Product\Export\Filter\ProductDateFilter;
use StoreAccountant\Product\Export\Filter\ProductVariantExportFilter;
use StoreAccountant\Export\Filter\Period\MonthYearPeriodProvider;
use StoreAccountant\Export\Filter\Period\PeriodProviderRegistry;
use StoreAccountant\Order\Export\Query\OrderQuery;
use StoreAccountant\Product\Export\Query\ProductQuery;
use StoreAccountant\Export\ExportRepository;
use StoreAccountant\Export\ExportStoragePathGenerator;
use StoreAccountant\Export\Queue\BatchExportStore;
use StoreAccountant\Export\Queue\ExportDetailLogger;
use StoreAccountant\Export\Queue\ExportQueueCleanup;
use StoreAccountant\Export\Queue\ExportTemporaryFilesCleanupSubscriber;
use StoreAccountant\Export\Queue\Handler\FinalizeExportAttachmentsMessageHandler;
use StoreAccountant\Export\Queue\Handler\FinalizeExportMessageHandler;
use StoreAccountant\Export\Queue\Handler\ProcessExportBatchMessageHandler;
use StoreAccountant\Export\Queue\Handler\StartExportMessageHandler;
use StoreAccountant\Export\Queue\Message\FinalizeExportAttachmentsMessage;
use StoreAccountant\Export\Queue\Message\FinalizeExportMessage;
use StoreAccountant\Export\Queue\Message\ProcessExportBatchMessage;
use StoreAccountant\Export\Queue\Message\StartExportMessage;
use StoreAccountant\Export\Queue\QueuedExportFinalizer;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\AmountMutator;
use StoreAccountant\Export\Field\Mutator\DateMutator;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;
use StoreAccountant\Export\Field\Meta\MetaFieldCollector;
use StoreAccountant\Export\Field\Meta\MetaFieldValueFormatter;
use StoreAccountant\Order\Export\Field\Provider\OrderFieldProvider;
use StoreAccountant\Order\Export\Field\Provider\OrderFieldValueProvider;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldProvider;
use StoreAccountant\Order\Export\Field\Provider\OrderMetaFieldValueProvider;
use StoreAccountant\Product\Export\Field\Provider\ProductFieldProvider;
use StoreAccountant\Product\Export\Field\Provider\ProductFieldValueProvider;
use StoreAccountant\Product\Export\Field\Provider\ProductMetaFieldProvider;
use StoreAccountant\Product\Export\Field\Provider\ProductMetaFieldValueProvider;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Tax\Admin\OrderTaxFieldProviderField;
use StoreAccountant\Tax\Field\Provider\ExtendedOrderTaxFieldProvider;
use StoreAccountant\Tax\Field\Provider\OrderTaxFieldValueProvider;
use StoreAccountant\Tax\Field\Provider\SimpleOrderTaxFieldProvider;
use StoreAccountant\Order\Tax\OrderTaxFieldProviderRegistry;
use StoreAccountant\Order\Tax\OrderTaxRateResolver;
use StoreAccountant\Order\Export\OrderStatusProvider;
use StoreAccountant\Export\Renderer\CsvExportRenderer;
use StoreAccountant\Export\Template\DefaultExportTemplateNormalizer;
use StoreAccountant\Export\Renderer\SerializerExportRendererRegistrar;
use StoreAccountant\Invoice\Export\Order\Attachment\InvoiceAttachmentProvider;
use StoreAccountant\Invoice\Export\Order\Configuration\InvoiceAttachmentConfigurationFieldProvider;
use StoreAccountant\Invoice\Export\Order\Field\InvoiceFieldProvider;
use StoreAccountant\Invoice\Export\Order\Field\InvoiceFieldValueProvider;
use StoreAccountant\Invoice\Export\Order\InvoiceExportAttachmentSettings;
use StoreAccountant\Invoice\InvoicePluginDetector;
use StoreAccountant\Invoice\InvoicePluginHelper;
use StoreAccountant\Invoice\InvoicePluginRegistry;
use StoreAccountant\Invoice\Plugin\WooCommercePdfInvoicesPackingSlipsPlugin;
use StoreAccountant\Security\Permission\CorePermissionActionProvider;
use StoreAccountant\Security\Permission\PermissionActionRegistry;
use StoreAccountant\Security\Permission\PermissionCapabilityRegistrar;
use StoreAccountant\Security\Permission\PermissionChecker;
use StoreAccountant\Security\Permission\RolePermissionRepository;
use StoreAccountant\Queue\Loopback\ActionSchedulerLoopbackRunner;
use StoreAccountant\Queue\Loopback\QueueLoopbackDispatcher;
use StoreAccountant\Queue\Loopback\QueueLoopbackEndpoint;
use StoreAccountant\Queue\Messenger\QueueMessageBus;
use StoreAccountant\Queue\QueueTransportRegistry;
use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportFactory;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportProcessor;
use StoreAccountant\Queue\Transport\ActionSchedulerTransportProvider;
use StoreAccountant\Queue\Transport\SyncTransportProvider;
use StoreAccountant\Security\ReversibleCrypto;
use StoreAccountant\Storage\Adapter\LocalStorageAdapter;
use StoreAccountant\Storage\Adapter\LocalStorageConfiguration;
use StoreAccountant\Storage\ProtectedUploadDirectory;
use StoreAccountant\Storage\StorageAdapterRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the plugin service container.
 */
final readonly class ContainerBuilder {
	/**
	 * Service IDs that register WordPress hooks during plugin boot.
	 *
	 * @var array<int, class-string<HookRegistrarInterface>>
	 */
	public const HOOK_SERVICES = [
		EventSubscriberRegistrar::class,
		CorePermissionActionProvider::class,
		PermissionCapabilityRegistrar::class,
		AdminAssets::class,
		AccountingMenu::class,
		ExportOverviewTabProvider::class,
		ExportConfigurationOverviewTabProvider::class,
		SupportOverviewTabProvider::class,
		AccountingSupportPage::class,
		SyncTransportProvider::class,
		ActionSchedulerTransportProvider::class,
		ActionSchedulerTransportProcessor::class,
		QueueLoopbackEndpoint::class,
		ExportQueueCleanup::class,
		CustomerExportAdapter::class,
		CustomerFieldProvider::class,
		CustomerFieldValueProvider::class,
		CustomerMetaFieldProvider::class,
		CustomerMetaFieldValueProvider::class,
		CustomerFieldMappingTabProvider::class,
		CustomerDateFilter::class,
		CustomerCountryFilter::class,
		CustomerDateFilterFieldProvider::class,
		CustomerCountryFilterFieldProvider::class,
		ProductExportAdapter::class,
		ProductFieldProvider::class,
		ProductFieldValueProvider::class,
		ProductMetaFieldProvider::class,
		ProductMetaFieldValueProvider::class,
		ProductFieldMappingTabProvider::class,
		ProductDateFilter::class,
		ProductVariantExportFilter::class,
		ProductDateFilterFieldProvider::class,
		ProductVariantExportFieldProvider::class,
		OrderExportAdapter::class,
		OrderFieldProvider::class,
		OrderFieldValueProvider::class,
		OrderMetaFieldProvider::class,
		OrderMetaFieldValueProvider::class,
		WooCommercePdfInvoicesPackingSlipsPlugin::class,
		InvoiceFieldProvider::class,
		InvoiceFieldValueProvider::class,
		InvoiceAttachmentProvider::class,
		InvoiceAttachmentConfigurationFieldProvider::class,
		SimpleOrderTaxFieldProvider::class,
		ExtendedOrderTaxFieldProvider::class,
		OrderTaxFieldValueProvider::class,
		AmountMutator::class,
		DateMutator::class,
		OrderFieldMappingTabProvider::class,
		CsvExportRenderer::class,
		SerializerExportRendererRegistrar::class,
		LocalStorageAdapter::class,
		MonthYearPeriodProvider::class,
		OrderDateFilter::class,
		OrderStatusFilter::class,
		OrderDateFilterFieldProvider::class,
		OrderStatusFilterFieldProvider::class,
		ExportDetailsReadTabProvider::class,
		ExportRawDataReadTabProvider::class,
		ExportLogReadTabProvider::class,
		ExportListPollingAjaxController::class,
		ExportDownloadController::class,
		ExportPostType::class,
		ExportConfigurationPostType::class,
		StorageActivationNotice::class,
		DiagnosticSettingsTabProvider::class,
		DiagnosticIncidentDownloadController::class,
		PluginSettingsPage::class,
		AccountingExportPage::class,
		ExportConfigurationPage::class,
	];

	/**
	 * Builds the configured container.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function build(): Container {
		$container = ( new Container(
			new DefinitionAggregate(),
			new ServiceProviderAggregate(),
			new InflectorAggregate()
		) )->defaultToShared();

		$this->register_services( $container );

		return $container;
	}

	/**
	 * Registers plugin services.
	 *
	 * Value objects such as ExportPeriod are intentionally not registered here.
	 *
	 * @param Container $container Service container.
	 */
	private function register_services( Container $container ): void {
		$container->addShared( PermissionActionRegistry::class );
		$container->addShared( PermissionChecker::class )
			->addArgument( PermissionActionRegistry::class );
		$container->addShared( RolePermissionRepository::class )
			->addArgument( PermissionActionRegistry::class );
		$container->addShared( CorePermissionActionProvider::class );
			$container->addShared( PermissionCapabilityRegistrar::class )
				->addArgument( RolePermissionRepository::class );
			$container->addShared( ExportEventLogSubscriber::class )
				->addArgument( ExportRepository::class );
			$container->addShared( ExportTemporaryFilesCleanupSubscriber::class )
				->addArgument( BatchExportStore::class );
			$container->addShared( EventSubscriberRegistrar::class )
				->addArgument( ExportEventLogSubscriber::class )
				->addArgument( ExportTemporaryFilesCleanupSubscriber::class );
			$container->addShared(
				MessengerTransportSerializerInterface::class,
				static fn (): MessengerTransportSerializerInterface => new MessengerTransportSerializer()
			);
		$container->addShared(
			TransportInterface::class,
			function () use ( $container ): TransportInterface {
				$provider = $container->get( QueueTransportRegistry::class )->get_active();

				return null !== $provider
					? $provider->create_transport( $container->get( MessengerTransportSerializerInterface::class ) )
					: new ActionSchedulerTransport( 'exports', $container->get( MessengerTransportSerializerInterface::class ) );
			}
		);
		$container->addShared( ActionSchedulerTransportFactory::class );
		$container->addShared( SyncTransportProvider::class )
			->addArgument(
				static fn () => $container->get( HandlersLocatorInterface::class )
			);
		$container->addShared( ActionSchedulerTransportProvider::class );
		$container->addShared( QueueTransportRegistry::class );
			$container->addShared( QueueLoopbackDispatcher::class )
				->addArgument( QueueTransportRegistry::class );
		$container->addShared( ActionSchedulerLoopbackRunner::class )
			->addArgument( ExportRepository::class );
			$container->addShared( QueueLoopbackEndpoint::class )
				->addArgument( QueueLoopbackDispatcher::class )
				->addArgument( ActionSchedulerLoopbackRunner::class );
		$container->addShared( ActionSchedulerTransportProcessor::class )
			->addArgument( MessengerTransportSerializerInterface::class )
			->addArgument( HandlersLocatorInterface::class );
		$container->addShared( MessageBusInterface::class, QueueMessageBus::class )
			->addArgument( TransportInterface::class );
		$container->addShared(
			HandlersLocatorInterface::class,
			function () use ( $container ): HandlersLocatorInterface {
				return new HandlersLocator(
					[
						StartExportMessage::class        => [
							$container->get( StartExportMessageHandler::class ),
						],
						ProcessExportBatchMessage::class => [
							$container->get( ProcessExportBatchMessageHandler::class ),
						],
						FinalizeExportMessage::class     => [
							$container->get( FinalizeExportMessageHandler::class ),
						],
						FinalizeExportAttachmentsMessage::class => [
							$container->get( FinalizeExportAttachmentsMessageHandler::class ),
						],
					]
				);
			}
		);
		$container->addShared( ExportFilterSelectionSerializer::class );
		$container->addShared( ReversibleCrypto::class );
		$container->addShared( DownloadPasswordManager::class )
			->addArgument( ReversibleCrypto::class );
		$container->addShared( ExportDownloadUrlFactory::class );
		$container->addShared( StorageFileStreamer::class );
		$container->addShared( ExportRepository::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( DownloadPasswordManager::class );
		$container->addShared( ExportFilterRegistry::class );
		$container->addShared( ExportFilterFieldProviderRegistry::class );
		$container->addShared( PeriodProviderRegistry::class );
		$container->addShared( ExportFilterSnapshotter::class )
			->addArgument( PeriodProviderRegistry::class );
		$container->addShared( OrderStatusProvider::class );
		$container->addShared( ExportConfigurationRepository::class )
			->addArgument( ExportFilterSelectionSerializer::class );
		$container->addShared( OrderQuery::class )
			->addArgument( ExportFilterRegistry::class );
		$container->addShared( CustomerQuery::class )
			->addArgument( ExportFilterRegistry::class );
		$container->addShared( ProductQuery::class )
			->addArgument( ExportFilterRegistry::class );
		$container->addShared( FieldMappingRepository::class );
		$container->addShared( MetaFieldCollector::class );
		$container->addShared( MetaFieldValueFormatter::class );
		$container->addShared( ExportAdapterRegistry::class );
		$container->addShared( ExportRendererRegistry::class );
		$container->addShared( ExportReadTabProviderRegistry::class );
		$container->addShared( ExportConfigurationTabProviderRegistry::class );
		$container->addShared( PluginSettingsTabProviderRegistry::class );
		$container->addShared( AccountingOverviewTabProviderRegistry::class );
		$container->addShared( AccountingSupportAccess::class );
		$container->addShared( DiagnosticSettings::class );
		$container->addShared( ProtectedUploadDirectory::class );
		$container->addShared(
			DiagnosticLogConfiguration::class,
			static function (): DiagnosticLogConfiguration {
				$configuration = DiagnosticLogConfiguration::from_wordpress_uploads();

				if ( is_wp_error( $configuration ) ) {
					return new DiagnosticLogConfiguration( '', 'wp-content/uploads/' . DiagnosticLogConfiguration::RELATIVE_PATH );
				}

				return $configuration;
			}
		);
		$container->addShared( DiagnosticIncidentRepository::class )
			->addArgument( DiagnosticLogConfiguration::class )
			->addArgument( ProtectedUploadDirectory::class );
		$container->addShared( DiagnosticIncidentLogger::class )
			->addArgument( DiagnosticSettings::class )
			->addArgument( DiagnosticIncidentRepository::class );
		$container->addShared( ExportDetailLogger::class )
			->addArgument( DiagnosticSettings::class )
			->addArgument( DiagnosticIncidentRepository::class )
			->addArgument( DiagnosticLogConfiguration::class );
		$container->addShared( FieldProviderRegistry::class );
		$container->addShared( FieldValueProviderRegistry::class );
		$container->addShared( ExportAttachmentProviderRegistry::class );
		$container->addShared( InvoicePluginRegistry::class );
		$container->addShared( InvoicePluginHelper::class );
		$container->addShared( InvoicePluginDetector::class )
			->addArgument( InvoicePluginRegistry::class );
		$container->addShared( InvoiceExportAttachmentSettings::class );
		$container->addShared( OrderTaxFieldProviderRegistry::class );
		$container->addShared( OrderTaxRateResolver::class );
		$container->addShared( OrderTaxFieldProviderField::class )
			->addArgument( OrderTaxFieldProviderRegistry::class );
		$container->addShared( FieldValueMutatorRegistry::class );
		$container->addShared( ExportFieldResolver::class )
			->addArgument( FieldProviderRegistry::class )
			->addArgument( FieldMappingRepository::class );
		$container->addShared( ExportDatasetBuilder::class )
			->addArgument( FieldValueProviderRegistry::class )
			->addArgument( FieldValueMutatorRegistry::class )
			->addArgument( ExportFieldResolver::class )
			->addArgument( ExportAttachmentProviderRegistry::class );
		$container->addShared(
			LocalStorageConfiguration::class,
			static function (): LocalStorageConfiguration {
				$configuration = LocalStorageConfiguration::from_wordpress_uploads();

				if ( is_wp_error( $configuration ) ) {
					return new LocalStorageConfiguration( '', 'wp-content/uploads/' . LocalStorageConfiguration::RELATIVE_PATH );
				}

				return $configuration;
			}
		);
		$container->addShared( ExportStoragePathGenerator::class )
			->addArgument( LocalStorageConfiguration::class );
		$container->addShared( ExportConfigurationFormFieldProviderRegistry::class );
		$container->addShared( StorageAdapterRegistry::class );
		$container->addShared( QueueTransportsSettingsForm::class )
			->addArgument( QueueTransportRegistry::class );
		$container->addShared( MonthYearExportPeriodFieldProvider::class );
		$container->addShared( MonthYearPeriodProvider::class );
		$container->addShared( OrderDateFilter::class )
			->addArgument( PeriodProviderRegistry::class );
		$container->addShared( CustomerDateFilter::class )
			->addArgument( PeriodProviderRegistry::class );
		$container->addShared( ProductDateFilter::class )
			->addArgument( PeriodProviderRegistry::class );
		$container->addShared( ProductVariantExportFilter::class );
		$container->addShared( CustomerCountryFilter::class );
		$container->addShared( OrderStatusFilter::class )
			->addArgument( OrderStatusProvider::class );
		$container->addShared( OrderStatusField::class )
			->addArgument( OrderStatusProvider::class );
		$container->addShared( OrderDateFilterFieldProvider::class )
			->addArgument( MonthYearExportPeriodFieldProvider::class );
		$container->addShared( CustomerDateFilterFieldProvider::class )
			->addArgument( MonthYearExportPeriodFieldProvider::class );
		$container->addShared( ProductDateFilterFieldProvider::class )
			->addArgument( MonthYearExportPeriodFieldProvider::class );
		$container->addShared( ProductVariantExportFieldProvider::class );
		$container->addShared( CustomerCountryFilterFieldProvider::class );
		$container->addShared( OrderStatusFilterFieldProvider::class )
			->addArgument( OrderStatusField::class );
		$container->addShared( ExportDetailsReadTabProvider::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportStoragePathGenerator::class )
			->addArgument( ExportDownloadUrlFactory::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportLogReadTabProvider::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportListPollingResponseFactory::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportDownloadUrlFactory::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportListPollingAjaxController::class )
			->addArgument( ExportListPollingResponseFactory::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportRawDataReadTabProvider::class )
			->addArgument( ExportFilterSelectionSerializer::class );
		$container->addShared( AdminAssets::class );
		$container->addShared( AccountingMenu::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportOverviewTabProvider::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportConfigurationOverviewTabProvider::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( SupportOverviewTabProvider::class )
			->addArgument( AccountingSupportAccess::class );
		$container->addShared( CustomerExportAdapter::class )
			->addArgument( CustomerQuery::class );
		$container->addShared( ProductExportAdapter::class )
			->addArgument( ProductQuery::class );
		$container->addShared( OrderExportAdapter::class )
			->addArgument( OrderTaxRateResolver::class )
			->addArgument( OrderQuery::class );
		$container->addShared( OrderFieldProvider::class );
		$container->addShared( OrderFieldValueProvider::class );
		$container->addShared( CustomerFieldProvider::class );
		$container->addShared( CustomerFieldValueProvider::class );
		$container->addShared( ProductFieldProvider::class );
		$container->addShared( ProductFieldValueProvider::class );
		$container->addShared( CustomerMetaFieldProvider::class )
			->addArgument( MetaFieldCollector::class );
		$container->addShared( CustomerMetaFieldValueProvider::class )
			->addArgument( MetaFieldValueFormatter::class );
		$container->addShared( ProductMetaFieldProvider::class )
			->addArgument( MetaFieldCollector::class );
		$container->addShared( ProductMetaFieldValueProvider::class )
			->addArgument( MetaFieldValueFormatter::class );
		$container->addShared( OrderMetaFieldProvider::class )
			->addArgument( MetaFieldCollector::class );
		$container->addShared( OrderMetaFieldValueProvider::class )
			->addArgument( MetaFieldValueFormatter::class );
		$container->addShared( WooCommercePdfInvoicesPackingSlipsPlugin::class )
			->addArgument( InvoicePluginHelper::class );
		$container->addShared( InvoiceFieldProvider::class )
			->addArgument( InvoicePluginDetector::class );
		$container->addShared( InvoiceFieldValueProvider::class )
			->addArgument( InvoicePluginDetector::class )
			->addArgument( InvoiceExportAttachmentSettings::class );
		$container->addShared( InvoiceAttachmentProvider::class )
			->addArgument( InvoicePluginDetector::class )
			->addArgument( InvoiceExportAttachmentSettings::class )
			->addArgument( FieldMappingRepository::class );
		$container->addShared( InvoiceAttachmentConfigurationFieldProvider::class )
			->addArgument( InvoicePluginDetector::class );
		$container->addShared( SimpleOrderTaxFieldProvider::class );
		$container->addShared( ExtendedOrderTaxFieldProvider::class )
			->addArgument( OrderTaxRateResolver::class );
		$container->addShared( OrderTaxFieldValueProvider::class )
			->addArgument( OrderTaxFieldProviderRegistry::class );
		$container->addShared( AmountMutator::class );
		$container->addShared( DateMutator::class );
		$container->addShared( DefaultExportTemplateNormalizer::class );
		$container->addShared(
			SerializerInterface::class,
			static fn (): SerializerInterface => new Serializer(
				[],
				[
					new JsonEncoder(),
					new CsvEncoder(),
					new XmlEncoder(),
				]
			)
		);
		$container->addShared( OrderFieldMappingTabProvider::class )
			->addArgument( ExportFieldResolver::class )
			->addArgument( FieldMappingRepository::class )
			->addArgument( OrderTaxFieldProviderRegistry::class )
			->addArgument( OrderTaxRateResolver::class )
			->addArgument( OrderQuery::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportFilterSnapshotter::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DiagnosticIncidentLogger::class );
		$container->addShared( CustomerFieldMappingTabProvider::class )
			->addArgument( ExportFieldResolver::class )
			->addArgument( FieldMappingRepository::class )
			->addArgument( CustomerQuery::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportFilterSnapshotter::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DiagnosticIncidentLogger::class );
		$container->addShared( ProductFieldMappingTabProvider::class )
			->addArgument( ExportFieldResolver::class )
			->addArgument( FieldMappingRepository::class )
			->addArgument( ProductQuery::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportFilterSnapshotter::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DiagnosticIncidentLogger::class );
		$container->addShared( CsvExportRenderer::class );
		$container->addShared( SerializerExportRendererRegistrar::class )
			->addArgument( DefaultExportTemplateNormalizer::class )
			->addArgument( SerializerInterface::class );
		$container->addShared( LocalStorageAdapter::class )
			->addArgument( LocalStorageConfiguration::class )
			->addArgument( ProtectedUploadDirectory::class );
		$container->addShared( DiagnosticSettingsTabProvider::class )
			->addArgument( DiagnosticSettings::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DiagnosticIncidentRepository::class );
		$container->addShared( DiagnosticIncidentDownloadController::class )
			->addArgument( DiagnosticIncidentRepository::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( AccountingHeaderBar::class )
			->addArgument( PermissionChecker::class )
			->addArgument( AccountingOverviewTabProviderRegistry::class );
		$container->addShared( AccountingSupportPage::class )
			->addArgument( AccountingHeaderBar::class )
			->addArgument( AccountingSupportAccess::class );
		$container->addShared( AccountingExportPageForm::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportFilterFieldProviderRegistry::class )
			->addArgument( ExportSettingsFields::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( ExportSettingsFields::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportConfigurationFormFieldProviderRegistry::class )
			->addArgument( OrderTaxFieldProviderField::class );
		$container->addShared( ExportConfigurationPageForm::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportFilterFieldProviderRegistry::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportSettingsFields::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( StorageLocationsForm::class )
			->addArgument( StorageAdapterRegistry::class );
		$container->addShared( SecuritySettingsForm::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( InvoicePluginForm::class )
			->addArgument( InvoicePluginRegistry::class );
		$container->addShared( PermissionsSettingsForm::class )
			->addArgument( PermissionActionRegistry::class )
			->addArgument( RolePermissionRepository::class );
		$container->addShared( Exporter::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportDatasetBuilder::class )
			->addArgument( ExportRepository::class )
			->addArgument( ExportStoragePathGenerator::class )
			->addArgument( ExportFilterSelectionSerializer::class );
		$container->addShared( BatchExportStore::class );
		$container->addShared( QueuedExportFinalizer::class )
			->addArgument( BatchExportStore::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportRepository::class )
			->addArgument( ExportStoragePathGenerator::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportDetailLogger::class );
		$container->addShared( StartExportMessageHandler::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRepository::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportDatasetBuilder::class )
			->addArgument( BatchExportStore::class )
			->addArgument( ExportDetailLogger::class );
		$container->addShared( ProcessExportBatchMessageHandler::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRepository::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportDatasetBuilder::class )
			->addArgument( BatchExportStore::class )
			->addArgument( ExportDetailLogger::class );
		$container->addShared( FinalizeExportMessageHandler::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( QueuedExportFinalizer::class )
			->addArgument( ExportRepository::class )
			->addArgument( ExportDetailLogger::class );
		$container->addShared( FinalizeExportAttachmentsMessageHandler::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportRepository::class )
			->addArgument( BatchExportStore::class )
			->addArgument( ExportDetailLogger::class );
		$container->addShared( ExportQueueCleanup::class )
			->addArgument( ExportRepository::class )
			->addArgument( BatchExportStore::class );
		$container->addShared( ExportPostType::class )
			->addArgument( AccountingHeaderBar::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportStoragePathGenerator::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportReadTabProviderRegistry::class )
			->addArgument( PermissionChecker::class )
			->addArgument( ExportRepository::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( QueueLoopbackDispatcher::class )
			->addArgument( ExportListPollingResponseFactory::class )
			->addArgument( ExportDownloadUrlFactory::class )
			->addArgument( DiagnosticIncidentLogger::class );
		$container->addShared( ExportDownloadController::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( StorageFileStreamer::class );
		$container->addShared( ExportConfigurationPostType::class )
			->addArgument( AccountingHeaderBar::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( PermissionChecker::class );
		$container->addShared( StorageActivationNotice::class );
		$container->addShared( PluginSettingsPage::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( StorageLocationsForm::class )
			->addArgument( InvoicePluginRegistry::class )
			->addArgument( InvoicePluginForm::class )
			->addArgument( QueueTransportsSettingsForm::class )
			->addArgument( PermissionsSettingsForm::class )
			->addArgument( SecuritySettingsForm::class )
			->addArgument( PluginSettingsTabProviderRegistry::class )
			->addArgument( RolePermissionRepository::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DownloadPasswordManager::class );
		$container->addShared( AccountingExportPage::class )
			->addArgument( AccountingExportPageForm::class )
			->addArgument( ExportRepository::class )
			->addArgument( MessageBusInterface::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportFilterFieldProviderRegistry::class )
			->addArgument( ExportSettingsFields::class )
			->addArgument( ExportFilterSelectionSerializer::class )
			->addArgument( ExportFilterSnapshotter::class )
			->addArgument( AccountingHeaderBar::class )
			->addArgument( PermissionChecker::class )
			->addArgument( QueueLoopbackDispatcher::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( DiagnosticIncidentLogger::class );
		$container->addShared( ExportConfigurationPage::class )
			->addArgument( ExportConfigurationPageForm::class )
			->addArgument( ExportConfigurationRepository::class )
			->addArgument( StorageAdapterRegistry::class )
			->addArgument( ExportAdapterRegistry::class )
			->addArgument( ExportRendererRegistry::class )
			->addArgument( ExportFilterFieldProviderRegistry::class )
			->addArgument( AccountingHeaderBar::class )
			->addArgument( ExportConfigurationTabProviderRegistry::class )
			->addArgument( ExportSettingsFields::class )
			->addArgument( PermissionChecker::class )
			->addArgument( DownloadPasswordManager::class )
			->addArgument( DiagnosticIncidentLogger::class );
	}
}
