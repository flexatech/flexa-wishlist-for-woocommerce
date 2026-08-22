<?php
/**
 * Plugin Name:       Flexa Wishlist for WooCommerce
 * Description:       A mobile-first, cache-safe wishlist for WooCommerce: one-tap save, guest persistence, silent account merge, a beautiful wishlist page, and effortless sharing.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.0
 * Author:            FlexaTech
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flexa-wishlist-for-woocommerce
 * Domain Path:       /i18n/languages
 *
 * @package Flexa\Wishlist
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Flexa Wishlist requires PHP 8.2 or higher. The plugin has been disabled.', 'flexa-wishlist-for-woocommerce' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'FLEXA_WISHLIST_VERSION', '1.0.0' );
define( 'FLEXA_WISHLIST_FILE', __FILE__ );
define( 'FLEXA_WISHLIST_PATH', plugin_dir_path( __FILE__ ) );
define( 'FLEXA_WISHLIST_URL', plugin_dir_url( __FILE__ ) );
define( 'FLEXA_WISHLIST_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLEXA_WISHLIST_REST_NAMESPACE', 'flexa-wishlist/v1' );
define( 'FLEXA_WISHLIST_TEXT_DOMAIN', 'flexa-wishlist-for-woocommerce' );

if ( file_exists( FLEXA_WISHLIST_PATH . 'vendor/autoload.php' ) ) {
	require_once FLEXA_WISHLIST_PATH . 'vendor/autoload.php';
}

// Fallback autoloader for both the Free (src/) and Pro (src-pro/) trees so the
// plugin runs without a composer install. Registered even when the Composer
// autoloader is present so Pro classes resolve from src-pro/ by file presence.
spl_autoload_register(
	static function ( string $class ): void {
		$map = [
			'Flexa\\Wishlist\\'    => FLEXA_WISHLIST_PATH . 'src/',
			'Flexa\\WishlistPro\\' => FLEXA_WISHLIST_PATH . 'src-pro/',
		];
		foreach ( $map as $prefix => $base_dir ) {
			if ( ! str_starts_with( $class, $prefix ) ) {
				continue;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require $file;
			}
			return;
		}
	}
);

register_activation_hook( __FILE__, [ \Flexa\Wishlist\Install\Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \Flexa\Wishlist\Install\Deactivator::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		// WooCommerce is a hard dependency; degrade gracefully with a notice
		// rather than fatally when it is inactive.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-warning"><p>';
					echo esc_html__( 'Flexa Wishlist requires WooCommerce to be installed and active.', 'flexa-wishlist-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		\Flexa\Wishlist\Plugin::instance()->boot();
	}
);

// Declare HPOS (custom order tables) compatibility.
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
