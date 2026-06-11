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

namespace StoreAccountant\Tests\Unit\Export;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Attachment\ExportAttachment;
use StoreAccountant\Export\Attachment\ExportAttachmentProviderRegistry;
use StoreAccountant\Export\Contract\ExportAdapterInterface;
use StoreAccountant\Export\Contract\ExportAttachmentProviderInterface;
use StoreAccountant\Export\Contract\FieldProviderInterface;
use StoreAccountant\Export\Contract\FieldValueMutatorInterface;
use StoreAccountant\Export\Contract\FieldValueProviderInterface;
use StoreAccountant\Export\ExportContext;
use StoreAccountant\Export\ExportDatasetBuilder;
use StoreAccountant\Export\ExportFieldResolver;
use StoreAccountant\Export\ExportPayload;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\FieldCollection;
use StoreAccountant\Export\Field\FieldProviderRegistry;
use StoreAccountant\Export\Field\FieldValue;
use StoreAccountant\Export\Field\FieldValueProviderRegistry;
use StoreAccountant\Export\Field\Mapping\FieldMappingRepository;
use StoreAccountant\Export\Field\Mutator\FieldValueMutatorRegistry;
use WP_Error;

/**
 * Tests normalized export dataset building.
 */
final class ExportDatasetBuilderTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'is_wp_error' )->alias( static fn ( mixed $value ): bool => $value instanceof WP_Error );
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_build_returns_adapter_errors_without_resolving_fields(): void {
		$error   = new WP_Error( 'items_failed', 'Items failed' );
		$adapter = $this->createMock( ExportAdapterInterface::class );
		$adapter->method( 'get_items' )->willReturn( $error );

		Functions\expect( 'apply_filters' )->never();

		self::assertSame( $error, $this->builder()->build( $adapter, new ExportPayload( 7, 'orders' ) ) );
	}

	public function test_build_from_items_combines_fields_values_mutators_and_attachments(): void {
		$attachment_stream = fopen( 'php://temp', 'rb+' );
		self::assertIsResource( $attachment_stream );

		$adapter = $this->adapter();
		$payload = new ExportPayload(
			7,
			'orders',
			[],
			[ ExportPayload::OPTION_INCLUDE_ATTACHMENTS => true ]
		);

		$field_provider      = $this->field_provider( [ new Field( 'name', 'Name' ), new Field( 'amount', 'Amount' ) ] );
		$value_provider      = $this->value_provider(
			[
				'name'   => new FieldValue( 'name', 'first' ),
				'amount' => new FieldValue( 'amount', '12' ),
			]
		);
		$mutator             = $this->mutator();
		$attachment_provider = $this->attachment_provider(
			new ExportAttachment( $attachment_stream, 'invoice.pdf', 'application/pdf', 'Invoices/invoice.pdf' )
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, array $items ) use ( $field_provider, $value_provider, $mutator, $attachment_provider ): array {
				return match ( $hook ) {
					'storeaccountant_export_field_provider' => [ $field_provider ],
					'storeaccountant_export_field_value_provider' => [ $value_provider ],
					'storeaccountant_export_field_value_mutator' => [ $mutator ],
					'storeaccountant_export_attachment_provider' => [ $attachment_provider ],
					default => $items,
				};
			}
		);

		$dataset = $this->builder()->build_from_items( $adapter, $payload, [ [ 'id' => 1001 ], [ 'id' => 1002 ] ] );

		self::assertSame( [ 'name', 'amount', 'extra' ], $dataset->fields->ids() );
		self::assertSame( 'orders', $dataset->options['type'] );
		self::assertCount( 2, $dataset->records );
		self::assertSame( '1001', $dataset->records[0]->id );
		self::assertSame( 'first', $dataset->records[0]->get_value( 'name' ) );
		self::assertSame( 'mutated-12', $dataset->records[0]->get_value( 'amount' ) );
		self::assertSame( 'extra-1001', $dataset->records[0]->get_value( 'extra' ) );
		self::assertCount( 2, $dataset->attachments );
		self::assertSame( 'Invoices/invoice.pdf', $dataset->attachments[0]->internal_path );
	}

	public function test_build_from_items_uses_empty_values_for_missing_fields_and_skips_attachments_when_disabled(): void {
		$adapter = $this->adapter();

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, array $items ): array {
				return match ( $hook ) {
					'storeaccountant_export_field_provider' => [
						new class() implements FieldProviderInterface {
							public function get_id(): string {
								return 'fields';
							}

							public function supports( ExportContext $context ): bool {
								return true;
							}

							public function get_fields( ExportContext $context ): array {
								return [ new Field( 'missing', 'Missing' ) ];
							}
						},
					],
					'storeaccountant_export_field_value_provider',
					'storeaccountant_export_field_value_mutator',
					'storeaccountant_export_attachment_provider' => [],
					default => $items,
				};
			}
		);

		$dataset = $this->builder()->build_from_items( $adapter, new ExportPayload( 7, 'orders' ), [ [ 'id' => 1001 ] ] );

		self::assertSame( '', $dataset->records[0]->get_value( 'missing' ) );
		self::assertSame( [], $dataset->attachments );
	}

	private function builder(): ExportDatasetBuilder {
		return new ExportDatasetBuilder(
			new FieldValueProviderRegistry(),
			new FieldValueMutatorRegistry(),
			new ExportFieldResolver( new FieldProviderRegistry(), new FieldMappingRepository() ),
			new ExportAttachmentProviderRegistry()
		);
	}

	private function adapter(): ExportAdapterInterface {
		return new class() implements ExportAdapterInterface {
			public function get_id(): string {
				return 'orders';
			}

			public function get_items( ExportPayload $payload ): iterable|WP_Error {
				return [ [ 'id' => 1001 ] ];
			}

			public function get_context( ExportPayload $payload, iterable $items ): ExportContext {
				return new ExportContext( 'orders' );
			}

			public function get_additional_fields( ExportPayload $payload, ExportContext $context ): FieldCollection {
				return new FieldCollection( [ new Field( 'extra', 'Extra' ) ] );
			}

			public function get_additional_values( mixed $item, ExportPayload $payload, ExportContext $context ): array {
				return [ 'extra' => new FieldValue( 'extra', 'extra-' . (string) $item['id'] ) ];
			}

			public function get_record_id( mixed $item ): string {
				return (string) $item['id'];
			}
		};
	}

	/**
	 * @param array<int, Field> $fields Fields.
	 */
	private function field_provider( array $fields ): FieldProviderInterface {
		return new class( $fields ) implements FieldProviderInterface {
			public function __construct(
				private readonly array $fields
			) {}

			public function get_id(): string {
				return 'fields';
			}

			public function supports( ExportContext $context ): bool {
				return true;
			}

			public function get_fields( ExportContext $context ): array {
				return $this->fields;
			}
		};
	}

	/**
	 * @param array<string, FieldValue> $values Values.
	 */
	private function value_provider( array $values ): FieldValueProviderInterface {
		return new class( $values ) implements FieldValueProviderInterface {
			public function __construct(
				private readonly array $values
			) {}

			public function get_id(): string {
				return 'values';
			}

			public function supports( Field $field, ExportContext $context ): bool {
				return isset( $this->values[ $field->id ] );
			}

			public function get_values( mixed $item, FieldCollection $fields, ExportContext $context ): array {
				return array_intersect_key( $this->values, $fields->all() );
			}
		};
	}

	private function mutator(): FieldValueMutatorInterface {
		return new class() implements FieldValueMutatorInterface {
			public function get_id(): string {
				return 'amount_mutator';
			}

			public function supports( Field $field, ExportContext $context ): bool {
				return 'amount' === $field->id;
			}

			public function mutate( FieldValue $value, Field $field, array $settings, ExportContext $context ): FieldValue {
				return new FieldValue( $value->field_id, 'mutated-' . (string) $value->value, $value->options );
			}
		};
	}

	private function attachment_provider( ExportAttachment $attachment ): ExportAttachmentProviderInterface {
		return new class( $attachment ) implements ExportAttachmentProviderInterface {
			public function __construct(
				private readonly ExportAttachment $attachment
			) {}

			public function get_id(): string {
				return 'attachments';
			}

			public function supports( ExportContext $context ): bool {
				return true;
			}

			public function get_directory( ExportContext $context ): string {
				return 'Invoices';
			}

			public function get_attachments( mixed $item, ExportPayload $payload, ExportContext $context ): iterable {
				return [ $this->attachment ];
			}
		};
	}
}
