<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The wishlist counter (§10.8): a link to the wishlist page with a live badge.
 * Cache-safe — the badge renders empty and the script fills it from /state. The
 * badge is hidden at zero. Auto-injection into nav menus is opt-in (default off).
 */
final class CounterRenderer {
	/**
	 * Escaped counter markup. `data-fw-count` is the badge the script updates.
	 */
	public static function html(): string {
		$page_id = (int) Settings::get( 'general', 'page_id' );
		$url     = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );
		$icon    = ButtonRenderer::icon_svg( (string) Settings::get( 'appearance', 'icon' ) );
		$label   = __( 'View wishlist', 'flexa-woocommerce-wishlist' );

		return sprintf(
			'<a class="fw-counter" href="%1$s" data-fw-counter aria-label="%2$s">%3$s<span class="fw-counter__badge" data-fw-count hidden>0</span></a>',
			esc_url( (string) $url ),
			esc_attr( $label ),
			$icon
		);
	}

	/**
	 * Append the counter to nav menus when auto-injection is enabled (opt-in).
	 *
	 * @param string $items
	 * @return string
	 */
	public static function append_to_menu( $items ): string {
		$items = (string) $items;
		return $items . '<li class="menu-item fw-counter-menu-item">' . self::html() . '</li>';
	}
}
