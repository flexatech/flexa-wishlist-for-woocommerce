<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Support\Settings;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Storefront shortcodes — the single render pipeline every placement surface
 * (blocks, Elementor) wraps 1:1 (§10.10):
 * - [flexa_wishlist]         the wishlist page
 * - [flexa_wishlist_button]  a toggle button for a given/current product
 * - [flexa_wishlist_counter] the counter link + badge
 *
 * Each shortcode force-enqueues the assets so it works on any page.
 */
final class Shortcodes {
	use SingletonTrait;

	public function register(): void {
		add_shortcode( 'flexa_wishlist', [ $this, 'page' ] );
		add_shortcode( 'flexa_wishlist_button', [ $this, 'button' ] );
		add_shortcode( 'flexa_wishlist_counter', [ $this, 'counter' ] );

		if ( (bool) Settings::get( 'counter', 'auto_inject' ) ) {
			add_filter( 'wp_nav_menu_items', [ CounterRenderer::class, 'append_to_menu' ], 10, 1 );
		}
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function page( $atts = [] ): string {
		Assets::instance()->enqueue();
		$atts = shortcode_atts( [ 'layout' => '' ], (array) $atts, 'flexa_wishlist' );
		return PageRenderer::html( $atts );
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function button( $atts = [] ): string {
		$atts = shortcode_atts( [ 'product_id' => 0 ], (array) $atts, 'flexa_wishlist_button' );

		$product = $this->resolve_product( (int) $atts['product_id'] );
		if ( ! $product instanceof \WC_Product ) {
			return '';
		}

		Assets::instance()->enqueue();
		return ButtonRenderer::button_html( $product, 'shortcode' );
	}

	/**
	 * Resolve the product a button shortcode targets: an explicit `product_id`
	 * att, else the current loop/single global product. Reads WooCommerce's
	 * `$product` global without reassigning it.
	 */
	private function resolve_product( int $product_id ): ?\WC_Product {
		if ( $product_id <= 0 ) {
			global $product;
			$product_id = $product instanceof \WC_Product ? $product->get_id() : 0;
		}

		$resolved = $product_id > 0 ? wc_get_product( $product_id ) : null;
		return $resolved instanceof \WC_Product ? $resolved : null;
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public function counter( $atts = [] ): string {
		unset( $atts );
		Assets::instance()->enqueue();
		return CounterRenderer::html();
	}
}
