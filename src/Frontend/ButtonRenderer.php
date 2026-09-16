<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Support\Settings;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the wishlist toggle button on loop + single product surfaces.
 *
 * CACHE-SAFETY RULE (§10.2/§17.1): the markup is user-AGNOSTIC. It never encodes
 * saved state — the button always renders in the neutral "not saved" state and
 * the storefront script reconciles the real state from one GET /state call after
 * load. This is what makes the button correct behind full-page caches (§24 A3).
 */
final class ButtonRenderer {
	use SingletonTrait;

	public function register(): void {
		// Defer to init: reading Settings builds the defaults array, which calls
		// __() for the label defaults. Doing that before init trips WP 6.7's
		// "translation loaded too early" notice.
		add_action( 'init', [ $this, 'register_placements' ] );
	}

	public function register_placements(): void {
		if ( ! (bool) Settings::get( 'general', 'enabled' ) ) {
			return;
		}

		$loop = (string) Settings::get( 'button', 'position_loop' );
		if ( 'on_image' === $loop ) {
			add_action( 'woocommerce_before_shop_loop_item_title', [ $this, 'render_loop' ], 15 );
		} elseif ( 'after_add_to_cart' === $loop ) {
			add_action( 'woocommerce_after_shop_loop_item', [ $this, 'render_loop' ], 15 );
		}

		$single = (string) Settings::get( 'button', 'position_single' );
		switch ( $single ) {
			case 'before_add_to_cart':
				add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_single' ], 5 );
				break;
			case 'after_summary':
				add_action( 'woocommerce_after_single_product_summary', [ $this, 'render_single' ], 5 );
				break;
			case 'after_add_to_cart':
				add_action( 'woocommerce_after_add_to_cart_button', [ $this, 'render_single' ], 15 );
				break;
		}
	}

	public function render_loop(): void {
		global $product;
		if ( $product instanceof \WC_Product ) {
			echo self::button_html( $product, 'loop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function render_single(): void {
		global $product;
		if ( $product instanceof \WC_Product ) {
			echo self::button_html( $product, 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * The shared, escaped button markup. Used by hooks, the shortcode, blocks
	 * and Elementor so every surface renders the identical element (§10.10).
	 */
	public static function button_html( \WC_Product $product, string $context = 'single' ): string {
		$icon       = (string) Settings::get( 'appearance', 'icon' );
		$label_add  = (string) Settings::get( 'button', 'label_add' );
		$product_id = $product->get_id();

		// A variable product on a single page starts as the parent (variation 0);
		// the script updates the variation id as the shopper selects options.
		$classes = [ 'fw-btn', 'fw-btn--' . $context, 'fw-btn--icon-' . $icon ];

		$html = sprintf(
			'<button type="button" class="%1$s" data-fw-toggle data-fw-product="%2$d" data-fw-variation="0" aria-pressed="false" aria-label="%3$s" title="%3$s">%4$s<span class="fw-btn__label">%5$s</span></button>',
			esc_attr( implode( ' ', $classes ) ),
			(int) $product_id,
			esc_attr( $label_add ),
			self::icon_svg( $icon ),
			esc_html( $label_add )
		);

		$wrapper = sprintf(
			'<div class="fw-btn-wrap fw-btn-wrap--%1$s" data-fw-button>%2$s</div>',
			esc_attr( $context ),
			$html
		);

		/**
		 * Filter the rendered button markup.
		 *
		 * @param string      $wrapper The full button HTML.
		 * @param \WC_Product $product The product.
		 * @param string      $context loop|single|shortcode.
		 */
		return (string) apply_filters( 'flexa_wishlist/button_html', $wrapper, $product, $context );
	}

	/**
	 * Inline SVG for the chosen icon. aria-hidden — the button carries the label.
	 */
	public static function icon_svg( string $icon ): string {
		$paths = [
			'heart'    => '<path d="M12 21s-7.5-4.6-10-9.2C.6 9 1.6 5.6 4.7 4.6 6.8 4 9 4.9 12 8c3-3.1 5.2-4 7.3-3.4 3.1 1 4.1 4.4 2.7 7.2C19.5 16.4 12 21 12 21z"/>',
			'star'     => '<path d="M12 2l3 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.9 21l1.2-6.8-5-4.9 6.9-1L12 2z"/>',
			'bookmark' => '<path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/>',
		];
		$path  = $paths[ $icon ] ?? $paths['heart'];

		return sprintf(
			'<span class="fw-btn__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" focusable="false">%s</svg></span>',
			$path
		);
	}
}
