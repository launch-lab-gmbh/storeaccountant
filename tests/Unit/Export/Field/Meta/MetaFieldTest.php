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

namespace StoreAccountant\Tests\Unit\Export\Field\Meta;

use PHPUnit\Framework\TestCase;
use StoreAccountant\Export\Field\Field;
use StoreAccountant\Export\Field\Meta\MetaField;

/**
 * Tests metadata field conventions.
 */
final class MetaFieldTest extends TestCase {
	public function test_get_field_id_uses_prefix_and_stable_meta_key_hash(): void {
		self::assertSame(
			'order_meta_c2dda35d8ccf',
			MetaField::get_field_id( 'order_meta_', '_billing_vat_id' )
		);
	}

	public function test_is_meta_field_returns_true_for_scalar_meta_key_option(): void {
		$field = new Field(
			'order_meta_abc123',
			'VAT ID',
			options: [
				MetaField::OPTION_META_KEY => '_billing_vat_id',
			]
		);

		self::assertTrue( MetaField::is_meta_field( $field ) );
		self::assertTrue( MetaField::is_meta_field( $field, 'order_meta_' ) );
	}

	public function test_is_meta_field_returns_false_when_prefix_does_not_match(): void {
		$field = new Field(
			'customer_meta_abc123',
			'VAT ID',
			options: [
				MetaField::OPTION_META_KEY => '_billing_vat_id',
			]
		);

		self::assertFalse( MetaField::is_meta_field( $field, 'order_meta_' ) );
	}

	public function test_is_meta_field_returns_false_without_scalar_meta_key_option(): void {
		self::assertFalse( MetaField::is_meta_field( new Field( 'order_meta_abc123', 'VAT ID' ) ) );
		self::assertFalse(
			MetaField::is_meta_field(
				new Field(
					'order_meta_abc123',
					'VAT ID',
					options: [
						MetaField::OPTION_META_KEY => [ '_billing_vat_id' ],
					]
				)
			)
		);
	}
}
