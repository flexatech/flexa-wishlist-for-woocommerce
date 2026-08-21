<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Admin;

use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {
	use SingletonTrait;

	public const SLUG = 'flexa-wishlist';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_filter( 'plugin_action_links_' . FLEXA_WISHLIST_BASENAME, [ $this, 'action_links' ] );
	}

	/**
	 * @param array<int|string,string> $links
	 * @return array<int|string,string>
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Settings', 'flexa-woocommerce-wishlist' )
		);
		array_unshift( $links, $settings );
		return $links;
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Flexa Wishlist', 'flexa-woocommerce-wishlist' ),
			__( 'Wishlist', 'flexa-woocommerce-wishlist' ),
			\Flexa\Wishlist\Support\Capabilities::MANAGE,
			self::SLUG,
			[ $this, 'render_page' ],
			'dashicons-heart',
			58
		);
	}

	public function render_page(): void {
		$template = FLEXA_WISHLIST_PATH . 'views/admin-app.php';
		if ( is_readable( $template ) ) {
			require $template;
		}
	}
}
