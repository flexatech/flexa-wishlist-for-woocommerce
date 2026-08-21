<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration;

use Flexa\Wishlist\Frontend\Shortcodes;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg blocks (§10.10). Thin dynamic wrappers over the storefront shortcode
 * renderers so shortcodes, blocks and Elementor share one render path and can
 * never drift. No build step: blocks register in PHP with a render_callback and
 * the editor UI is a small vanilla script (ServerSideRender preview), keeping the
 * Free plugin free of a node toolchain on the block side.
 */
final class Blocks {
	use SingletonTrait;

	private const EDITOR_HANDLE = 'flexa-wishlist-blocks';

	public function register(): void {
		add_action( 'init', [ $this, 'register_blocks' ] );
	}

	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			self::EDITOR_HANDLE,
			FLEXA_WISHLIST_URL . 'assets/blocks/editor.js',
			[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
			FLEXA_WISHLIST_VERSION,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( self::EDITOR_HANDLE, 'flexa-woocommerce-wishlist' );
		}

		register_block_type(
			'flexa-wishlist/page',
			[
				'title'           => __( 'Wishlist', 'flexa-woocommerce-wishlist' ),
				'description'     => __( 'The full wishlist page.', 'flexa-woocommerce-wishlist' ),
				'category'        => 'woocommerce',
				'icon'            => 'heart',
				'editor_script'   => self::EDITOR_HANDLE,
				'supports'        => [
					'html'  => false,
					'align' => [ 'wide', 'full' ],
				],
				'attributes'      => [
					'layout' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'render_callback' => [ $this, 'render_page' ],
			]
		);

		register_block_type(
			'flexa-wishlist/button',
			[
				'title'           => __( 'Wishlist Button', 'flexa-woocommerce-wishlist' ),
				'description'     => __( 'A save-to-wishlist toggle for a product.', 'flexa-woocommerce-wishlist' ),
				'category'        => 'woocommerce',
				'icon'            => 'heart',
				'editor_script'   => self::EDITOR_HANDLE,
				'supports'        => [ 'html' => false ],
				'attributes'      => [
					'productId' => [
						'type'    => 'number',
						'default' => 0,
					],
				],
				'render_callback' => [ $this, 'render_button' ],
			]
		);

		register_block_type(
			'flexa-wishlist/counter',
			[
				'title'           => __( 'Wishlist Counter', 'flexa-woocommerce-wishlist' ),
				'description'     => __( 'A link to the wishlist with a saved-count badge.', 'flexa-woocommerce-wishlist' ),
				'category'        => 'woocommerce',
				'icon'            => 'heart',
				'editor_script'   => self::EDITOR_HANDLE,
				'supports'        => [ 'html' => false ],
				'render_callback' => [ $this, 'render_counter' ],
			]
		);
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_page( array $attributes ): string {
		$layout = isset( $attributes['layout'] ) ? (string) $attributes['layout'] : '';
		return Shortcodes::instance()->page( [ 'layout' => $layout ] );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_button( array $attributes ): string {
		$product_id = isset( $attributes['productId'] ) ? (int) $attributes['productId'] : 0;
		return Shortcodes::instance()->button( [ 'product_id' => (string) $product_id ] );
	}

	/**
	 * @param array<string,mixed> $attributes
	 */
	public function render_counter( array $attributes ): string {
		unset( $attributes );
		return Shortcodes::instance()->counter();
	}
}
