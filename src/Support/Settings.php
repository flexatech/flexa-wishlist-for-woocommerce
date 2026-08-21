<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the `flexa_wishlist_settings` option: the typed
 * schema, defaults, type-coerced reads, and the sanitizer used on every write.
 * The REST controllers, renderers, storefront config, jobs, and CLI all go
 * through here so the schema can never drift between callers (§11.4, §23.2).
 *
 * The option is a nested map of groups. Reads return the full shape merged over
 * defaults and coerced; writes accept a partial payload (any subset of groups /
 * keys) and merge over what is stored so a one-toggle save never wipes the rest.
 */
final class Settings {
	public const OPTION_KEY = 'flexa_wishlist_settings';

	public const PRESETS             = [ 'flexa', 'minimal', 'classic', 'custom' ];
	public const ICONS               = [ 'heart', 'star', 'bookmark' ];
	public const RADII               = [ 'none', 'sm', 'md', 'lg', 'full' ];
	public const PAGE_LAYOUTS        = [ 'grid', 'list' ];
	public const LOOP_POSITIONS      = [ 'on_image', 'after_add_to_cart', 'none' ];
	public const SINGLE_POSITIONS    = [ 'after_add_to_cart', 'before_add_to_cart', 'after_summary', 'none' ];
	public const VISIBILITIES        = [ 'private', 'shared', 'public' ];
	public const SHARE_CHANNELS      = [ 'email', 'whatsapp', 'x', 'facebook', 'pinterest' ];
	public const ATTRIBUTION_WINDOWS = [ 7, 14, 30, 60, 90 ];

	/**
	 * The full default settings payload, grouped. Everything ships tuned so the
	 * plugin looks and works right with zero configuration (§2, §28).
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return apply_filters(
			'flexa_wishlist/default_settings',
			[
				'general'       => [
					'enabled'           => true,
					'guest_wishlists'   => true,
					'retention_days'    => 30,
					'default_list_name' => __( 'Favorites', 'flexa-woocommerce-wishlist' ),
					'page_id'           => 0,
				],
				'appearance'    => [
					'preset'       => 'flexa',
					// Empty accent = inherit the theme / WooCommerce primary color
					// (best-effort detection with a safe neutral fallback). §11.5.
					'accent_color' => '',
					'icon'         => 'heart',
					'radius'       => 'md',
					'custom_css'   => '',
				],
				'button'        => [
					'position_loop'   => 'on_image',
					'position_single' => 'after_add_to_cart',
					'label_add'       => __( 'Add to Wishlist', 'flexa-woocommerce-wishlist' ),
					'label_added'     => __( 'In Wishlist', 'flexa-woocommerce-wishlist' ),
				],
				'page'          => [
					'layout'      => 'grid',
					'per_page'    => 24,
					'show_price'  => true,
					'show_stock'  => true,
					'add_to_cart' => true,
				],
				'sharing'       => [
					'enabled'         => true,
					'channels'        => self::SHARE_CHANNELS,
					'show_owner_name' => true,
					'allow_indexing'  => false,
				],
				'counter'       => [
					'auto_inject' => false,
				],
				// Pro surfaces; free ships them off. Schema lives here so the Pro
				// build never has to migrate the option.
				'notifications' => [
					'price_drop_enabled'    => false,
					'price_drop_threshold'  => 5,
					'back_in_stock_enabled' => false,
					'sender_name'           => '',
					'sender_email'          => '',
				],
				'analytics'     => [
					'attribution_window_days' => 30,
				],
				'advanced'      => [
					'remove_after_add_to_cart' => false,
					'show_quantity'            => false,
					'load_scripts_all_pages'   => false,
					'delete_data_on_uninstall' => false,
				],
			]
		);
	}

	/**
	 * Stored settings merged over defaults, every value coerced back to its
	 * declared type so a hand-edited or corrupt option can never hand a consumer
	 * the wrong type.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return self::coerce( $stored );
	}

	/**
	 * Read a single group with its default fallback.
	 *
	 * @return array<string, mixed>
	 */
	public static function group( string $group ): array {
		$all = self::all();
		$val = $all[ $group ] ?? [];

		return is_array( $val ) ? $val : [];
	}

	/**
	 * Read a single "group.key" value with its default fallback.
	 */
	public static function get( string $group, string $key ): mixed {
		$g = self::group( $group );

		return $g[ $key ] ?? null;
	}

	/**
	 * Sanitize an incoming (possibly partial) payload against the schema and
	 * merge it over the stored settings. Unknown groups/keys are dropped so
	 * arbitrary client input is never persisted.
	 *
	 * @param array<string, mixed> $incoming
	 * @return array<string, mixed> The full settings to persist.
	 */
	public static function sanitize_merge( array $incoming ): array {
		$current = self::all();

		foreach ( $incoming as $group => $values ) {
			if ( ! is_array( $values ) || ! isset( $current[ $group ] ) || ! is_array( $current[ $group ] ) ) {
				continue;
			}
			$current[ $group ] = self::sanitize_group( (string) $group, $values, $current[ $group ] );
		}

		return $current;
	}

	/**
	 * @param array<string, mixed> $stored
	 * @return array<string, mixed>
	 */
	private static function coerce( array $stored ): array {
		$out = self::defaults();

		foreach ( $out as $group => $defaults ) {
			if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) ) {
				$out[ $group ] = self::sanitize_group( (string) $group, $stored[ $group ], $defaults );
			}
		}

		return $out;
	}

	/**
	 * Coerce/sanitize one group's incoming values over its current values.
	 *
	 * @param array<string, mixed> $incoming
	 * @param array<string, mixed> $current
	 * @return array<string, mixed>
	 */
	private static function sanitize_group( string $group, array $incoming, array $current ): array {
		$out = $current;

		switch ( $group ) {
			case 'general':
				self::set_bool( $out, $incoming, 'enabled' );
				self::set_bool( $out, $incoming, 'guest_wishlists' );
				self::set_int( $out, $incoming, 'retention_days', 1, 3650 );
				self::set_text( $out, $incoming, 'default_list_name' );
				self::set_int( $out, $incoming, 'page_id', 0, PHP_INT_MAX );
				break;

			case 'appearance':
				self::set_enum( $out, $incoming, 'preset', self::PRESETS );
				self::set_color( $out, $incoming, 'accent_color' );
				self::set_enum( $out, $incoming, 'icon', self::ICONS );
				self::set_enum( $out, $incoming, 'radius', self::RADII );
				if ( array_key_exists( 'custom_css', $incoming ) ) {
					$out['custom_css'] = self::sanitize_css( (string) $incoming['custom_css'] );
				}
				break;

			case 'button':
				self::set_enum( $out, $incoming, 'position_loop', self::LOOP_POSITIONS );
				self::set_enum( $out, $incoming, 'position_single', self::SINGLE_POSITIONS );
				self::set_text( $out, $incoming, 'label_add' );
				self::set_text( $out, $incoming, 'label_added' );
				break;

			case 'page':
				self::set_enum( $out, $incoming, 'layout', self::PAGE_LAYOUTS );
				self::set_int( $out, $incoming, 'per_page', 1, 96 );
				self::set_bool( $out, $incoming, 'show_price' );
				self::set_bool( $out, $incoming, 'show_stock' );
				self::set_bool( $out, $incoming, 'add_to_cart' );
				break;

			case 'sharing':
				self::set_bool( $out, $incoming, 'enabled' );
				if ( array_key_exists( 'channels', $incoming ) && is_array( $incoming['channels'] ) ) {
					$out['channels'] = array_values(
						array_intersect( self::SHARE_CHANNELS, array_map( 'strval', $incoming['channels'] ) )
					);
				}
				self::set_bool( $out, $incoming, 'show_owner_name' );
				self::set_bool( $out, $incoming, 'allow_indexing' );
				break;

			case 'counter':
				self::set_bool( $out, $incoming, 'auto_inject' );
				break;

			case 'notifications':
				self::set_bool( $out, $incoming, 'price_drop_enabled' );
				self::set_int( $out, $incoming, 'price_drop_threshold', 1, 99 );
				self::set_bool( $out, $incoming, 'back_in_stock_enabled' );
				self::set_text( $out, $incoming, 'sender_name' );
				if ( array_key_exists( 'sender_email', $incoming ) ) {
					$email               = sanitize_email( (string) $incoming['sender_email'] );
					$out['sender_email'] = is_email( $email ) ? $email : '';
				}
				break;

			case 'analytics':
				if ( array_key_exists( 'attribution_window_days', $incoming ) ) {
					$days = (int) $incoming['attribution_window_days'];
					// Allow the preset windows plus any custom value in range.
					$out['attribution_window_days'] = max( 1, min( 365, $days ) );
				}
				break;

			case 'advanced':
				self::set_bool( $out, $incoming, 'remove_after_add_to_cart' );
				self::set_bool( $out, $incoming, 'show_quantity' );
				self::set_bool( $out, $incoming, 'load_scripts_all_pages' );
				self::set_bool( $out, $incoming, 'delete_data_on_uninstall' );
				break;
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $out
	 * @param array<string, mixed> $in
	 */
	private static function set_bool( array &$out, array $in, string $key ): void {
		if ( array_key_exists( $key, $in ) ) {
			$out[ $key ] = self::to_bool( $in[ $key ] );
		}
	}

	/**
	 * @param array<string, mixed> $out
	 * @param array<string, mixed> $in
	 */
	private static function set_int( array &$out, array $in, string $key, int $min, int $max ): void {
		if ( array_key_exists( $key, $in ) && is_numeric( $in[ $key ] ) ) {
			$out[ $key ] = max( $min, min( $max, (int) $in[ $key ] ) );
		}
	}

	/**
	 * @param array<string, mixed> $out
	 * @param array<string, mixed> $in
	 */
	private static function set_text( array &$out, array $in, string $key ): void {
		if ( array_key_exists( $key, $in ) ) {
			$out[ $key ] = sanitize_text_field( (string) $in[ $key ] );
		}
	}

	/**
	 * @param array<string, mixed> $out
	 * @param array<string, mixed> $in
	 * @param list<string|int>     $allowed
	 */
	private static function set_enum( array &$out, array $in, string $key, array $allowed ): void {
		if ( array_key_exists( $key, $in ) ) {
			$val = is_int( $allowed[0] ?? null ) ? (int) $in[ $key ] : (string) $in[ $key ];
			if ( in_array( $val, $allowed, true ) ) {
				$out[ $key ] = $val;
			}
		}
	}

	/**
	 * @param array<string, mixed> $out
	 * @param array<string, mixed> $in
	 */
	private static function set_color( array &$out, array $in, string $key ): void {
		if ( ! array_key_exists( $key, $in ) ) {
			return;
		}
		$raw = trim( (string) $in[ $key ] );
		if ( '' === $raw ) {
			$out[ $key ] = '';
			return;
		}
		$hex = sanitize_hex_color( $raw );
		if ( is_string( $hex ) && '' !== $hex ) {
			$out[ $key ] = $hex;
		}
	}

	/**
	 * Strip anything that isn't safe to echo inside a <style> block. Keeps the
	 * custom-CSS escape hatch (§11.5) from becoming an injection vector.
	 */
	private static function sanitize_css( string $css ): string {
		$css = wp_strip_all_tags( $css );
		// Disallow the sequence that would close the style element early.
		$css = str_ireplace( [ '</style', '<script', 'javascript:', 'expression(' ], '', $css );

		return trim( $css );
	}

	/**
	 * Deterministic boolean coercion mirroring rest_sanitize_boolean() semantics.
	 * Kept local so the schema never depends on a templated WP stub that static
	 * analysis cannot resolve from a mixed value.
	 */
	private static function to_bool( mixed $value ): bool {
		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value ) );
			if ( in_array( $value, [ 'false', '0', '', 'off', 'no' ], true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}
