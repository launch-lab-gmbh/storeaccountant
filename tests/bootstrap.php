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

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test bootstrap defines WordPress ABSPATH for plugin guards.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! class_exists( 'StoreAccountant' ) ) {
	/**
	 * Minimal plugin metadata double for unit tests that do not load the entry file.
	 */
	final readonly class StoreAccountant {
		public const PLUGIN_VERSION = '0.1.0';
		public const PHP_VERSION    = '8.2';
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WordPress error double for unit tests.
	 */
	class WP_Error {
		public function __construct(
			private readonly string $code = '',
			private readonly string $message = '',
			private readonly mixed $data = null
		) {}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WordPress post double for unit tests.
	 */
	class WP_Post {
		public int $ID = 0;

		public string $post_type = '';

		public string $post_title = '';

		public string $post_status = '';

		/**
		 * @param array<string, mixed> $data Post data.
		 */
		public function __construct( array $data = [] ) {
			foreach ( $data as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! class_exists( 'WC_DateTime' ) ) {
	/**
	 * Minimal WooCommerce date double for unit tests.
	 */
	class WC_DateTime {
		public function __construct(
			private readonly string $date
		) {}

		public function date( string $format ): string {
			return ( new DateTimeImmutable( $this->date ) )->format( $format );
		}
	}
}

if ( ! class_exists( 'WC_Order_Item_Tax' ) ) {
	/**
	 * Minimal WooCommerce order tax item double for unit tests.
	 */
	class WC_Order_Item_Tax {
		public function __construct(
			private readonly int $rate_id,
			private readonly float $rate_percent,
			private readonly float $tax_total = 0.0,
			private readonly float $shipping_tax_total = 0.0
		) {}

		public function get_rate_id(): int {
			return $this->rate_id;
		}

		public function get_rate_percent(): float {
			return $this->rate_percent;
		}

		public function get_tax_total(): float {
			return $this->tax_total;
		}

		public function get_shipping_tax_total(): float {
			return $this->shipping_tax_total;
		}
	}
}

if ( ! class_exists( 'WC_Order_Query' ) ) {
	/**
	 * Minimal WooCommerce order query double for unit tests.
	 */
	class WC_Order_Query {
		/**
		 * Order query results used by the next query instances.
		 *
		 * @var array<int, mixed>|object
		 */
		public static array|object $results = [];

		/**
		 * Captured constructor arguments.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $queries = [];

		/**
		 * @param array<string, mixed> $values Query values.
		 */
		public function __construct(
			private array $values = []
		) {
			self::$queries[] = $values;
		}

		public function set( string $key, mixed $value ): void {
			$this->values[ $key ] = $value;
			$last_index           = array_key_last( self::$queries );

			if ( null !== $last_index ) {
				self::$queries[ $last_index ][ $key ] = $value;
			}
		}

		public function get( string $key ): mixed {
			return $this->values[ $key ] ?? null;
		}

		public function get_orders(): array|object {
			return self::$results;
		}
	}
}

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Flexible WooCommerce order double for unit tests.
	 */
	class WC_Order {
		/**
		 * Order fixtures indexed by order ID.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $orders = [];

		/**
		 * @param array<int|string, mixed>|int $data      Order data, meta, or tax items, or order ID.
		 * @param array<int, mixed>|null   $tax_items Tax items.
		 */
		public function __construct(
			private array|int $data = [],
			private ?array $tax_items = null
		) {
			if ( null === $this->tax_items && is_array( $this->data ) && array_is_list( $this->data ) ) {
				$this->tax_items = $this->data;
				$this->data      = [];
			}
		}

		/**
		 * Gets normalized order data.
		 *
		 * @return array<string, mixed>
		 */
		private function get_data(): array {
			if ( is_int( $this->data ) ) {
				return self::$orders[ $this->data ] ?? [ 'id' => $this->data ];
			}

			return $this->data;
		}

		public function get_id(): int {
			return (int) ( $this->get_data()['id'] ?? 0 );
		}

		public function get_meta( string $key, bool $single = true ): mixed {
			return $this->get_data()[ $key ] ?? '';
		}

		public function get_items( string $type ): array {
			return 'tax' === $type ? ( $this->tax_items ?? [] ) : [];
		}

		public function get_fees(): array {
			return $this->get_data()['fees'] ?? [];
		}

		public function get_shipping_phone(): string {
			return (string) ( $this->get_data()['shipping_phone'] ?? '' );
		}

		public function __call( string $name, array $arguments ): mixed {
			if ( str_starts_with( $name, 'get_' ) ) {
				return $this->get_data()[ substr( $name, 4 ) ] ?? '';
			}

			return null;
		}
	}
}

if ( ! class_exists( 'WC_Customer' ) ) {
	/**
	 * Flexible WooCommerce customer double for unit tests.
	 */
	class WC_Customer {
		/**
		 * Customer fixtures indexed by customer ID.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $customers = [];

		/**
		 * @param array<string, mixed>|int $data Customer data or customer ID.
		 */
		public function __construct(
			private readonly array|int $data = []
		) {
		}

		/**
		 * Gets normalized customer data.
		 *
		 * @return array<string, mixed>
		 */
		private function get_data(): array {
			if ( is_int( $this->data ) ) {
				return self::$customers[ $this->data ] ?? [
					'id'          => $this->data,
					'order_count' => 0,
				];
			}

			return $this->data;
		}

		public function get_id(): int {
			return (int) ( $this->get_data()['id'] ?? 0 );
		}

		public function get_order_count(): int {
			return (int) ( $this->get_data()['order_count'] ?? 0 );
		}

		public function get_display_name(): string {
			return (string) ( $this->get_data()['display_name'] ?? '' );
		}

		public function get_date_modified(): ?WC_DateTime {
			$value = $this->get_data()['date_modified'] ?? null;

			return $value instanceof WC_DateTime ? $value : null;
		}

		public function get_shipping_phone(): string {
			return (string) ( $this->get_data()['shipping_phone'] ?? '' );
		}

		public function __call( string $name, array $arguments ): mixed {
			if ( str_starts_with( $name, 'get_' ) ) {
				return $this->get_data()[ substr( $name, 4 ) ] ?? '';
			}

			return null;
		}
	}
}

if ( ! class_exists( 'WC_Product_Attribute' ) ) {
	/**
	 * Flexible WooCommerce product attribute double for unit tests.
	 */
	class WC_Product_Attribute {
		/**
		 * @param array<int, mixed> $options Attribute options.
		 */
		public function __construct(
			private readonly string $name = '',
			private readonly bool $taxonomy = false,
			private readonly array $options = []
		) {}

		public function get_name(): string {
			return $this->name;
		}

		public function is_taxonomy(): bool {
			return $this->taxonomy;
		}

		/**
		 * @return array<int, mixed>
		 */
		public function get_options(): array {
			return $this->options;
		}
	}
}

if ( ! class_exists( 'WC_Product' ) ) {
	/**
	 * Flexible WooCommerce product double for unit tests.
	 */
	class WC_Product {
		/**
		 * Product fixtures indexed by product ID.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $products = [];

		/**
		 * @param array<string, mixed>|int $data Product data or product ID.
		 */
		public function __construct(
			protected readonly array|int $data = []
		) {}

		/**
		 * Gets normalized product data.
		 *
		 * @return array<string, mixed>
		 */
		protected function get_data(): array {
			if ( is_int( $this->data ) ) {
				return self::$products[ $this->data ] ?? [ 'id' => $this->data ];
			}

			return $this->data;
		}

		public function get_id(): int {
			return (int) ( $this->get_data()['id'] ?? 0 );
		}

		public function get_parent_id(): int {
			return (int) ( $this->get_data()['parent_id'] ?? 0 );
		}

		public function get_date_created(): ?WC_DateTime {
			$value = $this->get_data()['date_created'] ?? null;

			return $value instanceof WC_DateTime ? $value : null;
		}

		public function get_date_modified(): ?WC_DateTime {
			$value = $this->get_data()['date_modified'] ?? null;

			return $value instanceof WC_DateTime ? $value : null;
		}

		/**
		 * @return array<int|string, mixed>
		 */
		public function get_attributes(): array {
			return $this->get_data()['attributes'] ?? [];
		}

		/**
		 * @return array<string, mixed>
		 */
		public function get_default_attributes(): array {
			return $this->get_data()['default_attributes'] ?? [];
		}

		public function get_meta( string $key, bool $single = true ): mixed {
			return $this->get_data()['meta'][ $key ] ?? '';
		}

		public function get_meta_data(): array {
			$meta = $this->get_data()['meta'] ?? [];

			return array_map(
				static fn ( string $key, mixed $value ): object => new class( $key, $value ) {
					public function __construct(
						private readonly string $key,
						private readonly mixed $value
					) {}

					public function get_data(): array {
						return [
							'key'   => $this->key,
							'value' => $this->value,
						];
					}
				},
				array_keys( $meta ),
				$meta
			);
		}

		public function __call( string $name, array $arguments ): mixed {
			if ( str_starts_with( $name, 'get_' ) ) {
				return $this->get_data()[ substr( $name, 4 ) ] ?? '';
			}

			return null;
		}
	}
}

if ( ! class_exists( 'WC_Product_Variation' ) ) {
	/**
	 * Flexible WooCommerce product variation double for unit tests.
	 */
	class WC_Product_Variation extends WC_Product {
		/**
		 * @return array<string, mixed>
		 */
		public function get_variation_attributes(): array {
			return $this->get_data()['variation_attributes'] ?? [];
		}
	}
}

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Flexible WordPress query double for unit tests.
	 */
	class WP_Query {
		/**
		 * Query post results used by the next query instances.
		 *
		 * @var array<int, mixed>|array<int, array<int, mixed>>
		 */
		public static array $results = [];

		/**
		 * Query total used by paginated query instances.
		 */
		public static int $found_posts_result = 0;

		/**
		 * Captured constructor arguments.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $queries = [];

		/**
		 * Query posts.
		 *
		 * @var array<int, mixed>
		 */
		public array $posts = [];

		/**
		 * Query total.
		 */
		public int $found_posts = 0;

		/**
		 * @param array<string, mixed> $args Query args.
		 */
		public function __construct(
			private readonly array $args = []
		) {
			self::$queries[]    = $args;
			$this->found_posts = self::$found_posts_result;
			$this->posts       = $this->get_next_results();
		}

		/**
		 * Gets configured query results.
		 *
		 * @return array<int, mixed>
		 */
		private function get_next_results(): array {
			if ( [] === self::$results ) {
				return [];
			}

			if ( isset( self::$results[0] ) && is_array( self::$results[0] ) ) {
				return array_shift( self::$results );
			}

			return self::$results;
		}
	}
}

if ( ! class_exists( 'WP_User_Query' ) ) {
	/**
	 * Flexible WordPress user query double for unit tests.
	 */
	class WP_User_Query {
		/**
		 * User query results used by the next query instances.
		 *
		 * @var array<int, mixed>|array<int, array<int, mixed>>
		 */
		public static array $results = [];

		/**
		 * Captured constructor arguments.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public static array $queries = [];

		/**
		 * @param array<string, mixed> $args Query args.
		 */
		public function __construct(
			private readonly array $args = []
		) {
			self::$queries[] = $args;
		}

		/**
		 * Gets configured query results.
		 *
		 * @return array<int, mixed>
		 */
		public function get_results(): array {
			if ( [] === self::$results ) {
				return [];
			}

			if ( isset( self::$results[0] ) && is_array( self::$results[0] ) ) {
				return array_shift( self::$results );
			}

			return self::$results;
		}
	}
}
