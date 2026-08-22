<?php
/**
 * Constants defined by the plugin bootstrap, declared here so PHPStan can
 * resolve them without running WordPress.
 *
 * @package Flexa\Wishlist
 */

declare(strict_types=1);

define( 'FLEXA_WISHLIST_VERSION', '1.0.0' );
define( 'FLEXA_WISHLIST_FILE', __FILE__ );
define( 'FLEXA_WISHLIST_PATH', __DIR__ . '/' );
define( 'FLEXA_WISHLIST_URL', 'https://example.test/wp-content/plugins/flexa-wishlist-for-woocommerce/' );
define( 'FLEXA_WISHLIST_BASENAME', 'flexa-wishlist-for-woocommerce/flexa-wishlist-for-woocommerce.php' );
define( 'FLEXA_WISHLIST_REST_NAMESPACE', 'flexa-wishlist/v1' );
define( 'FLEXA_WISHLIST_TEXT_DOMAIN', 'flexa-wishlist-for-woocommerce' );
