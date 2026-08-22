<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration\Elementor;

use Elementor\Widget_Base;
use Flexa\Wishlist\Frontend\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor "Wishlist Counter" widget — the wishlist link with a saved-count
 * badge. Renders via the shared shortcode. Excluded from PHPStan (extends a
 * stub-less base class).
 */
final class CounterWidget extends Widget_Base {
	public function get_name(): string {
		return 'flexa_wishlist_counter';
	}

	public function get_title(): string {
		return __( 'Wishlist Counter', 'flexa-wishlist-for-woocommerce' );
	}

	public function get_icon(): string {
		return 'eicon-heart';
	}

	/**
	 * @return string[]
	 */
	public function get_categories(): array {
		return [ 'flexa-wishlist' ];
	}

	/**
	 * @return string[]
	 */
	public function get_keywords(): array {
		return [ 'wishlist', 'counter', 'badge', 'favorites' ];
	}

	protected function render(): void {
		// Shortcode output is already escaped at source (renderer builds safe markup).
		echo Shortcodes::instance()->counter(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
