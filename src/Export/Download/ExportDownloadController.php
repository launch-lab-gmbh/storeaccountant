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

namespace StoreAccountant\Export\Download;

use StoreAccountant\Contract\HookRegistrarInterface;
use StoreAccountant\Contract\WordPress\Request;
use StoreAccountant\Export\ExportPostType;
use StoreAccountant\Export\ExportStatus;
use StoreAccountant\Storage\Contract\StorageAdapterInterface;
use StoreAccountant\Storage\StorageAdapterRegistry;
use function add_rewrite_rule;
use function add_query_arg;
use function array_filter;
use function array_map;
use function bloginfo;
use function esc_url;
use function esc_url_raw;
use function esc_html;
use function esc_html__;
use function explode;
use function get_post_meta;
use function get_posts;
use function get_query_var;
use function get_the_title;
use function home_url;
use function implode;
use function is_string;
use function is_wp_error;
use function language_attributes;
use function ltrim;
use function nocache_headers;
use function sanitize_file_name;
use function sanitize_key;
use function status_header;
use function str_replace;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function wp_footer;
use function wp_head;
use function wp_nonce_field;
use function __;
use function wp_verify_nonce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend password-protected export downloads.
 */
final readonly class ExportDownloadController implements HookRegistrarInterface {
	private const QUERY_VAR = 'storeaccountant_export_download';

	/**
	 * Internal StoreAccountant method.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function __construct(
		private StorageAdapterRegistry $storage_adapters,
		private DownloadPasswordManager $passwords,
		private StorageFileStreamer $streamer
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register(): void {
		add_action( 'init', [ $this, 'register_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ] );
	}

	/**
	 * Registers the pretty frontend download route.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function register_rewrite_rule(): void {
		add_rewrite_rule(
			'^storeaccountant/export-download/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Adds the token query var.
	 *
	 * @since 1.0.0
	 * @internal
	 *
	 * @param array<int, string> $query_vars Public query vars.
	 *
	 * @return array<int, string>
	 */
	public function register_query_var( array $query_vars ): array {
		$query_vars[] = self::QUERY_VAR;

		return $query_vars;
	}

	/**
	 * Handles a frontend download request.
	 *
	 * @since 1.0.0
	 * @internal
	 */
	public function handle_request(): void {
		$token = sanitize_key( (string) get_query_var( self::QUERY_VAR ) );

		if ( '' === $token ) {
			return;
		}

		$export_id = $this->get_export_id_by_token( $token );

		if ( null === $export_id ) {
			$this->render_message( __( 'The requested export is unavailable.', 'storeaccountant' ), 404 );
		}

		$availability = $this->get_availability_error( $export_id );

		if ( null !== $availability ) {
			$this->render_message( $availability, 404 );
		}

		$request_method = Request::server_key( 'REQUEST_METHOD', 'GET' );

		if ( 'post' === $request_method ) {
			$nonce = Request::post_text( 'storeaccountant_export_download_nonce' );

			if ( ! wp_verify_nonce( $nonce, 'storeaccountant_export_download_' . $export_id ) ) {
				$this->render_password_form( $export_id, __( 'The download request could not be verified.', 'storeaccountant' ) );
			}

			$password = Request::post_text( 'storeaccountant_export_download_password' );

			if ( $this->passwords->verify( $password, (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_PASSWORD_HASH, true ) ) ) {
				$this->stream_export( $export_id );
			}

			$this->render_password_form( $export_id, __( 'The entered password is incorrect.', 'storeaccountant' ) );
		}

		$this->render_password_form( $export_id );
	}

	/**
	 * Finds an export by its public token.
	 */
	private function get_export_id_by_token( string $token ): ?int {
		$exports = get_posts(
			[
				'fields'         => 'ids',
				'post_type'      => ExportPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			]
		);

		foreach ( $exports as $export_id ) {
			if ( (string) get_post_meta( (int) $export_id, ExportPostType::META_DOWNLOAD_TOKEN, true ) === $token ) {
				return (int) $export_id;
			}
		}

		return null;
	}

	/**
	 * Gets an availability error, if the export cannot be downloaded.
	 */
	private function get_availability_error( int $export_id ): ?string {
		if ( ExportStatus::COMPLETED !== (string) get_post_meta( $export_id, ExportPostType::META_STATUS, true ) ) {
			return __( 'The requested export is not ready for download.', 'storeaccountant' );
		}

		if ( '' === (string) get_post_meta( $export_id, ExportPostType::META_DOWNLOAD_PASSWORD_HASH, true ) ) {
			return __( 'This export cannot be downloaded because password protection is unavailable.', 'storeaccountant' );
		}

		$storage_path = $this->normalize_storage_path( (string) get_post_meta( $export_id, ExportPostType::META_PATH, true ) );

		if ( '' === $storage_path ) {
			return __( 'The requested export file is unavailable.', 'storeaccountant' );
		}

		$storage_adapter = $this->get_storage_adapter( $export_id );

		if ( null === $storage_adapter || ! $storage_adapter->file_exists( $storage_path ) ) {
			return __( 'The requested export file is unavailable.', 'storeaccountant' );
		}

		return null;
	}

	/**
	 * Streams the stored export file through the selected adapter.
	 */
	private function stream_export( int $export_id ): void {
		$storage_adapter = $this->get_storage_adapter( $export_id );
		$storage_path    = $this->normalize_storage_path( (string) get_post_meta( $export_id, ExportPostType::META_PATH, true ) );

		if ( null === $storage_adapter || '' === $storage_path || ! $storage_adapter->file_exists( $storage_path ) ) {
			$this->render_message( __( 'The requested export file is unavailable.', 'storeaccountant' ), 404 );
		}

		$file = $storage_adapter->get_file( $storage_path );

		if ( is_wp_error( $file ) ) {
			$this->render_message( __( 'The requested export file is unavailable.', 'storeaccountant' ), 404 );
		}

		$this->streamer->stream( $file );
	}

	/**
	 * Renders the password form.
	 */
	private function render_password_form( int $export_id, string $error = '' ): void {
		status_header( 200 );
		nocache_headers();
		$title = get_the_title( $export_id );
		$title = is_string( $title ) && '' !== $title ? $title : __( 'Accounting Export', 'storeaccountant' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php echo esc_html( $title ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body>
			<main style="max-width: 32rem; margin: 4rem auto; padding: 0 1rem;">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $error ) : ?>
					<p role="alert"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( $this->get_current_url() ); ?>">
					<?php wp_nonce_field( 'storeaccountant_export_download_' . $export_id, 'storeaccountant_export_download_nonce' ); ?>
					<p>
						<label for="storeaccountant-export-download-password"><?php esc_html_e( 'Download Password', 'storeaccountant' ); ?></label><br />
						<input type="password" id="storeaccountant-export-download-password" name="storeaccountant_export_download_password" required="required" />
					</p>
					<p>
						<button type="submit"><?php esc_html_e( 'Download', 'storeaccountant' ); ?></button>
					</p>
				</form>
			</main>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Renders an unavailable message.
	 */
	private function render_message( string $message, int $status = 404 ): void {
		status_header( $status );
		nocache_headers();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1" />
			<title><?php esc_html_e( 'Export Unavailable', 'storeaccountant' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body>
			<main style="max-width: 32rem; margin: 4rem auto; padding: 0 1rem;">
				<h1><?php esc_html_e( 'Export Unavailable', 'storeaccountant' ); ?></h1>
				<p><?php echo esc_html( $message ); ?></p>
			</main>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Gets the sanitized current frontend URL.
	 */
	private function get_current_url(): string {
		$request_uri = esc_url_raw( Request::server_text( 'REQUEST_URI' ) );

		return home_url( ltrim( $request_uri, '/' ) );
	}

	/**
	 * Gets the storage adapter for an export.
	 */
	private function get_storage_adapter( int $export_id ): ?StorageAdapterInterface {
		$storage_engine = (string) get_post_meta( $export_id, ExportPostType::META_STORAGE_ENGINE, true );

		return '' !== $storage_engine ? $this->storage_adapters->get( sanitize_key( $storage_engine ) ) : null;
	}

	/**
	 * Normalizes stored storage paths.
	 */
	private function normalize_storage_path( string $storage_path ): string {
		$separator_position = strrpos( $storage_path, '#' );

		if ( false === $separator_position ) {
			return $storage_path;
		}

		$archive_path = $this->normalize_storage_archive_path( substr( $storage_path, 0, $separator_position ) );

		if ( '' === $archive_path ) {
			return ltrim( substr( $storage_path, $separator_position + 1 ), '/' );
		}

		return $archive_path;
	}

	/**
	 * Normalizes a stored archive path.
	 */
	private function normalize_storage_archive_path( string $archive_path ): string {
		$archive_path = str_replace( '\\', '/', $archive_path );
		$marker       = '/storeaccountant/';
		$marker_pos   = strpos( $archive_path, $marker );

		if ( false !== $marker_pos ) {
			$archive_path = substr( $archive_path, $marker_pos + strlen( $marker ) );
		}

		$segments = array_filter(
			explode( '/', $archive_path ),
			static fn ( string $segment ): bool => '' !== $segment && '.' !== $segment && '..' !== $segment
		);
		$segments = array_map( 'sanitize_file_name', $segments );
		$segments = array_filter(
			$segments,
			static fn ( string $segment ): bool => '' !== $segment
		);

		return implode( '/', $segments );
	}
}
