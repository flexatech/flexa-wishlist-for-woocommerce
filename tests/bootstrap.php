<?php
/**
 * PHPUnit bootstrap for the Flexa Wishlist unit suite.
 *
 * These are true unit tests of the plugin's pure/near-pure logic (value objects
 * + the settings schema), not WordPress integration tests. Rather than pull in
 * the full WP test library + a database, we define the handful of WordPress
 * functions the classes under test actually call. Anything that needs $wpdb or
 * WooCommerce (repositories, cart, REST) is out of scope for this suite and
 * belongs in a future integration suite.
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
define( 'FLEXA_WISHLIST_TESTS', true );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// --- Minimal WordPress function shims --------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string { // phpcs:ignore
		unset( $domain );
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param mixed $value
	 * @param mixed ...$args
	 * @return mixed
	 */
	function apply_filters( string $tag, $value, ...$args ) { // phpcs:ignore
		unset( $tag, $args );
		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Reads from a per-test in-memory store so a test can seed stored settings.
	 *
	 * @param mixed $default_value
	 * @return mixed
	 */
	function get_option( string $key, $default_value = false ) { // phpcs:ignore
		$store = $GLOBALS['__fw_options'] ?? [];
		return array_key_exists( $key, $store ) ? $store[ $key ] : $default_value;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $string, bool $remove_breaks = false ): string { // phpcs:ignore
		$string = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = wp_kses_no_tags( $string );
		if ( $remove_breaks ) {
			$string = (string) preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

if ( ! function_exists( 'wp_kses_no_tags' ) ) {
	function wp_kses_no_tags( string $string ): string { // phpcs:ignore
		return (string) preg_replace( '/<[^>]*>/', '', $string );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string { // phpcs:ignore
		$str = wp_strip_all_tags( $str );
		return trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $str ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): ?string { // phpcs:ignore
		if ( '' === $color ) {
			return '';
		}
		return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ? $color : null;
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string { // phpcs:ignore
		return trim( (string) filter_var( $email, FILTER_SANITIZE_EMAIL ) );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * @return string|false
	 */
	function is_email( string $email ) { // phpcs:ignore
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}
