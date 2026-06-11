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

namespace StoreAccountant\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Expectation\Exception\ExpectationArgsRequired;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\I18n;

/**
 * Tests translation helper behavior.
 */
final class I18nTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_translate_registry_label_returns_translation_for_known_key(): void {
		Functions\when( '__' )->returnArg( 1 );

		self::assertSame(
			'export_adapter_orders',
			I18n::translate_registry_label( 'export_adapter_', 'orders' )
		);
	}

	/**
	 * Tests unknown registry label formatting.
	 *
	 * @throws ExpectationArgsRequired
	 */
	public function test_translate_registry_label_formats_unknown_id(): void {
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 'my-custom_export' )
			->andReturn( 'my-custom_export' );

		self::assertSame(
			'My Custom Export',
			I18n::translate_registry_label( 'export_adapter_', 'my-custom_export' )
		);
	}
}
