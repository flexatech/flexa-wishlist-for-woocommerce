<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Integration\ShareRoute;
use Flexa\Wishlist\Support\Settings;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Conditional storefront asset loading (§17.2). One dependency-free script and
 * one stylesheet, enqueued only where a wishlist surface can appear. Presets are
 * expressed as `--fw-*` CSS custom properties injected inline so theming needs
 * no rebuild; the accent defaults to the theme/Woo primary color (§11.5).
 */
final class Assets {
	use SingletonTrait;

	public const SCRIPT = 'flexa-wishlist';
	public const STYLE  = 'flexa-wishlist';

	private bool $enqueued = false;

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ], 20 );
	}

	public function register_assets(): void {
		wp_register_style(
			self::STYLE,
			FLEXA_WISHLIST_URL . 'assets/frontend/flexa-wishlist.css',
			[],
			FLEXA_WISHLIST_VERSION
		);

		wp_register_script(
			self::SCRIPT,
			FLEXA_WISHLIST_URL . 'assets/frontend/flexa-wishlist.js',
			[],
			FLEXA_WISHLIST_VERSION,
			true
		);
	}

	public function maybe_enqueue(): void {
		if ( ! (bool) Settings::get( 'general', 'enabled' ) ) {
			return;
		}
		if ( $this->should_load() ) {
			$this->enqueue();
		}
	}

	private function should_load(): bool {
		if ( (bool) Settings::get( 'advanced', 'load_scripts_all_pages' ) ) {
			return true;
		}
		if ( (bool) Settings::get( 'counter', 'auto_inject' ) ) {
			return true;
		}

		$is_wc            = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_account_page() );
		$is_wishlist_page = $this->is_wishlist_page();

		return (bool) apply_filters( 'flexa_wishlist/should_load_assets', $is_wc || $is_wishlist_page );
	}

	public function is_wishlist_page(): bool {
		$page_id = (int) Settings::get( 'general', 'page_id' );
		return $page_id > 0 && is_page( $page_id );
	}

	/**
	 * Force enqueue (called by shortcodes/blocks that can appear on any page).
	 */
	public function enqueue(): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;

		wp_enqueue_style( self::STYLE );
		wp_enqueue_script( self::SCRIPT );

		wp_add_inline_style( self::STYLE, $this->preset_css() );

		wp_localize_script( self::SCRIPT, 'flexaWishlistFront', $this->config() );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function config(): array {
		$page_id = (int) Settings::get( 'general', 'page_id' );

		return apply_filters(
			'flexa_wishlist/js_config',
			[
				'restUrl'     => esc_url_raw( rest_url( FLEXA_WISHLIST_REST_NAMESPACE . '/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'wishlistUrl' => $page_id > 0 ? get_permalink( $page_id ) : '',
				'shareBase'   => ShareRoute::share_base(),
				'stateTtl'    => 60,
				'emitEvents'  => true,
				'cartEnabled' => (bool) Settings::get( 'page', 'add_to_cart' ),
				'labels'      => [
					'add'   => (string) Settings::get( 'button', 'label_add' ),
					'added' => (string) Settings::get( 'button', 'label_added' ),
				],
				'i18n'        => [
					'saved'       => __( 'Saved to your wishlist.', 'flexa-wishlist-for-woocommerce' ),
					'removed'     => __( 'Removed from your wishlist.', 'flexa-wishlist-for-woocommerce' ),
					'undo'        => __( 'Undo', 'flexa-wishlist-for-woocommerce' ),
					'retry'       => __( 'Retry', 'flexa-wishlist-for-woocommerce' ),
					'error'       => __( 'Something went wrong. Please try again.', 'flexa-wishlist-for-woocommerce' ),
					'viewList'    => __( 'View wishlist', 'flexa-wishlist-for-woocommerce' ),
					'cart'        => __( 'Add to cart', 'flexa-wishlist-for-woocommerce' ),
					'select'      => __( 'Select options', 'flexa-wishlist-for-woocommerce' ),
					'remove'      => __( 'Remove', 'flexa-wishlist-for-woocommerce' ),
					'oos'         => __( 'Out of stock', 'flexa-wishlist-for-woocommerce' ),
					'addedCart'   => __( 'Added to cart.', 'flexa-wishlist-for-woocommerce' ),
					'viewCart'    => __( 'View cart', 'flexa-wishlist-for-woocommerce' ),
					'addAll'      => __( 'Add all to cart', 'flexa-wishlist-for-woocommerce' ),
					'addingAll'   => __( 'Adding…', 'flexa-wishlist-for-woocommerce' ),
					'someSkipped' => __( 'Some items could not be added.', 'flexa-wishlist-for-woocommerce' ),
				],
			]
		);
	}

	/**
	 * Preset design tokens as CSS custom properties + the merchant's custom CSS.
	 * Accent empty ⇒ inherit the theme/Woo primary via a var() fallback chain.
	 */
	private function preset_css(): string {
		$preset = (string) Settings::get( 'appearance', 'preset' );
		$accent = (string) Settings::get( 'appearance', 'accent_color' );
		$radius = (string) Settings::get( 'appearance', 'radius' );

		$radius_map   = [
			'none' => '0',
			'sm'   => '4px',
			'md'   => '8px',
			'lg'   => '14px',
			'full' => '999px',
		];
		$radius_value = $radius_map[ $radius ] ?? '8px';

		// Best-effort theme primary: WooCommerce button color, then a neutral.
		$wc_primary   = get_option( 'woocommerce_button_background_color' );
		$fallback     = is_string( $wc_primary ) && '' !== $wc_primary ? $wc_primary : '#e0245e';
		$accent_value = '' !== $accent ? $accent : 'var(--wp--preset--color--primary, ' . $fallback . ')';

		$vars = sprintf(
			':root{--fw-accent:%1$s;--fw-radius:%2$s;}',
			$accent_value,
			$radius_value
		);

		$custom = (string) Settings::get( 'appearance', 'custom_css' );

		$css = "/* preset: {$preset} */\n" . $vars;
		if ( '' !== $custom ) {
			$css .= "\n" . $custom;
		}

		return $css;
	}
}
