<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration;

use Flexa\Wishlist\Frontend\ShareView;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Pretty share permalinks: /wishlist/{slug} (§20.1). Implemented with a rewrite
 * rule + query var so the shared view is a normal, cacheable, indexable-when-
 * allowed URL. The actual render is delegated to the ShareView renderer.
 */
final class ShareRoute {
	use SingletonTrait;

	public const QUERY_VAR = 'flexa_wl_share';
	public const BASE      = 'wishlist';

	public function register(): void {
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'add_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_render' ] );
	}

	public function add_rewrite(): void {
		$base = self::share_base();
		add_rewrite_rule( '^' . $base . '/([A-Za-z0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_render(): void {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! is_string( $slug ) || '' === $slug ) {
			return;
		}
		ShareView::instance()->render( $slug );
		exit;
	}

	public static function share_base(): string {
		return (string) apply_filters( 'flexa_wishlist/share/base', self::BASE );
	}

	public static function url_for( string $slug ): string {
		if ( get_option( 'permalink_structure' ) ) {
			return home_url( '/' . self::share_base() . '/' . $slug );
		}
		return add_query_arg( self::QUERY_VAR, $slug, home_url( '/' ) );
	}
}
