<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Install;

use Flexa\Wishlist\Support\Settings;

defined( 'ABSPATH' ) || exit;

final class Activator {
	public static function activate(): void {
		Migrator::migrate();

		if ( get_option( Settings::OPTION_KEY, null ) === null ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}

		self::ensure_wishlist_page();

		// Register the pretty share route, then flush so /wishlist/{slug} works
		// immediately after activation.
		flush_rewrite_rules();
	}

	/**
	 * Create the front-end wishlist page on first activation if the merchant
	 * hasn't chosen one yet, so the counter/menu has a destination out of the
	 * box (§28 zero-config).
	 */
	private static function ensure_wishlist_page(): void {
		$settings = Settings::all();
		$page_id  = (int) ( $settings['general']['page_id'] ?? 0 );

		if ( $page_id > 0 && 'page' === get_post_type( $page_id ) ) {
			return;
		}

		$existing = get_page_by_path( 'wishlist' );
		if ( $existing instanceof \WP_Post ) {
			$page_id = (int) $existing->ID;
		} else {
			$page_id = (int) wp_insert_post(
				[
					'post_title'   => __( 'Wishlist', 'flexa-wishlist-for-woocommerce' ),
					'post_name'    => 'wishlist',
					'post_content' => '<!-- wp:shortcode -->[flexa_wishlist]<!-- /wp:shortcode -->',
					'post_status'  => 'publish',
					'post_type'    => 'page',
				]
			);
		}

		if ( $page_id > 0 ) {
			$settings['general']['page_id'] = $page_id;
			update_option( Settings::OPTION_KEY, $settings );
		}
	}
}
