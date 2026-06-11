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

namespace {
	if ( ! class_exists( 'ActionScheduler_Store' ) ) {
		class ActionScheduler_Store {
			public const STATUS_PENDING = 'pending';
		}
	}

	if ( ! class_exists( 'ActionScheduler' ) ) {
		class ActionScheduler {
			public static object $runner;

			public static function runner(): object {
				return self::$runner;
			}
		}
	}
}

namespace StoreAccountant\Tests\Unit\Queue\Loopback {

	use Brain\Monkey;
	use Brain\Monkey\Functions;
	use PHPUnit\Framework\TestCase;
	use RuntimeException;
	use StoreAccountant\Export\Download\DownloadPasswordManager;
	use StoreAccountant\Export\ExportPostType;
	use StoreAccountant\Export\ExportRepository;
	use StoreAccountant\Export\ExportStatus;
	use StoreAccountant\Export\Filter\ExportFilterSelectionSerializer;
	use StoreAccountant\Queue\Loopback\ActionSchedulerLoopbackRunner;
	use StoreAccountant\Queue\Transport\ActionSchedulerTransport;
	use StoreAccountant\Security\ReversibleCrypto;
	use WP_Error;

	/**
	 * Tests Action Scheduler loopback runner processing.
	 */
	final class ActionSchedulerLoopbackRunnerTest extends TestCase {
		/** @var array<string, mixed> */
		private array $meta = [];

		/** @var array<int, int> */
		private array $processed_actions = [];

		protected function setUp(): void {
			parent::setUp();

			Monkey\setUp();

			$this->meta              = [ ExportPostType::META_STATUS => ExportStatus::PROCESSING ];
			$this->processed_actions = [];

			Functions\when( '__' )->returnArg( 1 );
			Functions\when( 'current_time' )->alias( static fn (): string => '2026-06-07 16:30:00' );
			Functions\when( 'sanitize_text_field' )->alias( static fn ( string $value ): string => $value );
			Functions\when( 'absint' )->alias( static fn ( mixed $value ): int => abs( (int) $value ) );
			Functions\when( 'do_action' )->justReturn();
			Functions\when( 'get_post_meta' )->alias( fn ( int $post_id, string $key ): mixed => $this->meta[ $key ] ?? '' );
			Functions\when( 'update_post_meta' )->alias(
				function ( int $post_id, string $key, mixed $value ): void {
					$this->meta[ $key ] = $value;
				}
			);
		}

		protected function tearDown(): void {
			Monkey\tearDown();

			parent::tearDown();
		}

		public function test_run_processes_due_export_action_and_stops_when_no_pending_actions_remain(): void {
			$actions_by_call = [
				[
					101 => new class() {
						public function get_args(): array {
							return [ 'export_id' => 42 ];
						}
					},
				],
				[],
				[],
				[],
				[],
			];

			Functions\when( 'as_get_scheduled_actions' )->alias(
				static function () use ( &$actions_by_call ): array {
					return array_shift( $actions_by_call ) ?? [];
				}
			);

			\ActionScheduler::$runner = new class( $this->processed_actions ) {
				/** @var array<int, int> */
				private array $processed_actions;

				/**
				 * @param array<int, int> $processed_actions Processed action IDs.
				 */
				public function __construct( array &$processed_actions ) {
					$this->processed_actions = &$processed_actions;
				}

				public function process_action( int $action_id, string $context ): void {
					$this->processed_actions[] = $action_id;
				}
			};

			self::assertFalse( $this->runner()->run( 42 ) );
			self::assertSame( [ 101 ], $this->processed_actions );
		}

		public function test_run_marks_export_failed_when_action_processing_throws(): void {
			Functions\when( 'as_get_scheduled_actions' )->alias(
				static fn (): array => [
					101 => new class() {
						public function get_args(): array {
							return [ 'export_id' => 42 ];
						}
					},
				]
			);

			\ActionScheduler::$runner = new class() {
				public function process_action( int $action_id, string $context ): void {
					throw new RuntimeException( 'Queue failed' );
				}
			};

			self::assertFalse( $this->runner()->run( 42 ) );
			self::assertSame( ExportStatus::FAILED, $this->meta[ ExportPostType::META_STATUS ] );
			self::assertSame( 'The accounting export queue runner failed.', $this->meta[ ExportPostType::META_ERROR_MESSAGE ] );
		}

		private function runner(): ActionSchedulerLoopbackRunner {
			return new ActionSchedulerLoopbackRunner(
				new ExportRepository(
					new ExportFilterSelectionSerializer(),
					new DownloadPasswordManager( new ReversibleCrypto() )
				)
			);
		}
	}
}
