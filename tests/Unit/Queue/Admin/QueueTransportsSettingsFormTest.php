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

namespace StoreAccountant\Tests\Unit\Queue\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use StoreAccountant\Queue\Admin\QueueTransportsSettingsForm;
use StoreAccountant\Queue\Contract\QueueTransportProviderInterface;
use StoreAccountant\Queue\QueueTransportRegistry;

/**
 * Tests queue transport settings form behavior.
 */
final class QueueTransportsSettingsFormTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias(
			static function ( string $text, string $domain = 'default' ): void {
				echo $text;
			}
		);
		Functions\when( 'sanitize_key' )->alias( static fn ( string $key ): string => strtolower( preg_replace( '/[^a-zA-Z0-9_\\-]/', '', $key ) ?? '' ) );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'checked' )->alias(
			static function ( string $current, string $expected ): void {
				if ( $current === $expected ) {
					echo 'checked="checked"';
				}
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();

		parent::tearDown();
	}

	public function test_save_from_request_validates_transport_id_against_registry(): void {
		$sync = $this->provider( 'sync', 'Synchronous', 'Runs immediately.' );

		Functions\expect( 'apply_filters' )
			->twice()
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ $sync ] );
		Functions\expect( 'update_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync', false );

		$form = new QueueTransportsSettingsForm( new QueueTransportRegistry() );
		$form->save_from_request( [ 'storeaccountant_queue_transport_provider' => 'sync' ] );
		$form->save_from_request( [ 'storeaccountant_queue_transport_provider' => 'missing' ] );

		$this->addToAssertionCount( 1 );
	}

	public function test_render_fields_outputs_active_and_available_transports(): void {
		$sync             = $this->provider( 'sync', 'Synchronous', 'Runs jobs inline.' );
		$action_scheduler = $this->provider( 'action_scheduler', 'Action Scheduler', 'Runs jobs in the background.' );

		Functions\expect( 'apply_filters' )
			->times( 3 )
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [ $sync, $action_scheduler ] );
		Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( 'action_scheduler' );

		$output = $this->render_form();

		self::assertStringContainsString( 'Synchronous', $output );
		self::assertStringContainsString( 'Action Scheduler', $output );
		self::assertStringContainsString( 'Runs jobs inline.', $output );
		self::assertStringContainsString( 'Runs jobs in the background.', $output );
		self::assertStringContainsString( 'id="storeaccountant-queue-transport-action_scheduler"', $output );
		self::assertStringContainsString( 'value="action_scheduler"', $output );
		self::assertStringContainsString( 'checked="checked"', $output );
	}

	public function test_render_fields_outputs_empty_state_without_transports(): void {
		Functions\expect( 'apply_filters' )
			->times( 3 )
			->with( 'storeaccountant_queue_transport_provider', [] )
			->andReturn( [] );
		Functions\expect( 'get_option' )
			->once()
			->with( QueueTransportRegistry::OPTION_NAME, 'sync' )
			->andReturn( 'sync' );

		$output = $this->render_form();

		self::assertStringContainsString( 'No queue transports are registered.', $output );
	}

	private function render_form(): string {
		ob_start();
		( new QueueTransportsSettingsForm( new QueueTransportRegistry() ) )->render_fields();

		return (string) ob_get_clean();
	}

	private function provider( string $id, string $label, string $description ): QueueTransportProviderInterface {
		$provider = $this->createMock( QueueTransportProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'get_label' )->willReturn( $label );
		$provider->method( 'get_description' )->willReturn( $description );

		return $provider;
	}
}
