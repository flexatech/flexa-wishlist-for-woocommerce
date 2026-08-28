<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Admin;

use Flexa\Wishlist\Support\Settings;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues the built React admin app on the plugin's screen only. Reads the Vite
 * manifest for prod; loads from the Vite dev server when FLEXA_WISHLIST_DEV is
 * defined or an apps/admin/.dev marker exists. Localizes window.flexaWishlist.
 */
final class Enqueue {
	use SingletonTrait;

	private const HANDLE = 'flexa-wishlist-admin';

	public function register(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
	}

	public function enqueue_admin( string $hook_suffix ): void {
		if ( 'toplevel_page_' . AdminMenu::SLUG !== $hook_suffix ) {
			return;
		}

		$entry  = 'src/main.tsx';
		$handle = self::HANDLE;

		if ( $this->is_dev_mode() ) {
			$this->enqueue_dev( $handle, $entry );
		} else {
			$this->enqueue_prod( $handle, $entry );
		}

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, FLEXA_WISHLIST_TEXT_DOMAIN, FLEXA_WISHLIST_PATH . 'i18n/languages' );
		}

		wp_localize_script( $handle, 'flexaWishlist', $this->config() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function config(): array {
		$page_id = (int) Settings::get( 'general', 'page_id' );

		return apply_filters(
			'flexa_wishlist/admin_config',
			[
				'restUrl'      => esc_url_raw( rest_url() ),
				'restBase'     => FLEXA_WISHLIST_REST_NAMESPACE,
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'version'      => FLEXA_WISHLIST_VERSION,
				'pluginUrl'    => esc_url_raw( FLEXA_WISHLIST_URL ),
				'locale'       => determine_locale(),
				'theme'        => $this->detect_admin_theme(),
				'settings'     => Settings::all(),
				'wishlistPage' => [
					'id'  => $page_id,
					'url' => $page_id > 0 ? get_permalink( $page_id ) : '',
				],
			]
		);
	}

	private function detect_admin_theme(): string {
		$scheme = (string) get_user_option( 'admin_color' );
		if ( '' === $scheme ) {
			$scheme = 'fresh';
		}
		$dark = [ 'midnight', 'ectoplasm', 'ocean', 'coffee' ];
		return in_array( $scheme, $dark, true ) ? 'dark' : 'light';
	}

	private function is_dev_mode(): bool {
		if ( defined( 'FLEXA_WISHLIST_DEV' ) && constant( 'FLEXA_WISHLIST_DEV' ) ) {
			return true;
		}
		return file_exists( FLEXA_WISHLIST_PATH . 'apps/admin/.dev' );
	}

	private function enqueue_dev( string $handle, string $entry ): void {
		$server = 'http://localhost:5173';
		// Vite dev server (HMR) — versionless by design; never runs in production.
		wp_enqueue_script( $handle . '-client', $server . '/@vite/client', [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( $handle, $server . '/' . $entry, [ 'wp-i18n' ], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		$this->as_module( [ $handle . '-client', $handle ] );
	}

	private function enqueue_prod( string $handle, string $entry ): void {
		$dist          = FLEXA_WISHLIST_PATH . 'assets/dist/';
		$manifest_path = $dist . '.vite/manifest.json';
		if ( ! is_readable( $manifest_path ) ) {
			$manifest_path = $dist . 'manifest.json';
		}
		if ( ! is_readable( $manifest_path ) ) {
			return;
		}

		// Local build manifest read (not a remote request); WP_Filesystem is
		// unavailable this early and adds nothing for a bundled read-only file.
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $manifest ) || ! isset( $manifest[ $entry ] ) || ! is_array( $manifest[ $entry ] ) ) {
			return;
		}

		$item     = $manifest[ $entry ];
		$dist_url = FLEXA_WISHLIST_URL . 'assets/dist/';

		foreach ( (array) ( $item['css'] ?? [] ) as $i => $css ) {
			wp_enqueue_style( $handle . '-' . $i, $dist_url . $css, [], FLEXA_WISHLIST_VERSION );
		}

		wp_enqueue_script( $handle, $dist_url . (string) ( $item['file'] ?? '' ), [ 'wp-i18n' ], FLEXA_WISHLIST_VERSION, true );
		$this->as_module( [ $handle ] );
	}

	/**
	 * @param string[] $handles
	 */
	private function as_module( array $handles ): void {
		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $h ) use ( $handles ): string {
				if ( in_array( $h, $handles, true ) && ! str_contains( $tag, 'type="module"' ) ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);
	}
}
