<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Product;

use Flexa\Wishlist\Domain\Item\Item;

defined( 'ABSPATH' ) || exit;

/**
 * Turns stored Item snapshots into render-ready payloads using LIVE WooCommerce
 * data (title, permalink, image, price HTML, stock, purchasability). The plugin
 * never formats prices itself — it renders WooCommerce's own price HTML so tax
 * and multi-currency plugins keep working (§16.3). Products that no longer exist
 * become "ghost" payloads the UI renders as remove-only cards (§9.14 / §16.1).
 */
final class ProductHydrator {
	/**
	 * @param Item[] $items
	 * @return array<int,array<string,mixed>>
	 */
	public function hydrate_many( array $items ): array {
		$out = [];
		foreach ( $items as $item ) {
			$out[] = $this->hydrate( $item );
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function hydrate( Item $item ): array {
		$base    = $item->to_array();
		$product = $this->resolve_product( $item );

		if ( ! $product instanceof \WC_Product ) {
			// Ghost: product deleted/unavailable. Only a Remove action applies.
			$base['ghost']   = true;
			$base['name']    = __( 'This product is no longer available', 'flexa-woocommerce-wishlist' );
			$base['product'] = null;
			return $base;
		}

		$base['ghost']   = false;
		$base['product'] = [
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'permalink'      => get_permalink( $product->get_id() ),
			'type'           => $product->get_type(),
			'image'          => $this->image( $product ),
			'priceHtml'      => $product->get_price_html(),
			'inStock'        => $product->is_in_stock(),
			'stockStatus'    => $product->get_stock_status(),
			'purchasable'    => $product->is_purchasable() && $product->is_in_stock(),
			'addToCartUrl'   => $product->add_to_cart_url(),
			'isExternal'     => $product->is_type( 'external' ),
			'needsSelection' => $this->needs_selection( $product, $item ),
		];

		return $base;
	}

	private function resolve_product( Item $item ): ?\WC_Product {
		$id      = $item->variation_id > 0 ? $item->variation_id : $item->product_id;
		$product = wc_get_product( $id );
		if ( ! $product instanceof \WC_Product ) {
			// Variation gone but parent may survive — fall back to the parent.
			$parent = wc_get_product( $item->product_id );
			return $parent instanceof \WC_Product ? $parent : null;
		}
		return $product;
	}

	/**
	 * @return array{src:string,alt:string}
	 */
	private function image( \WC_Product $product ): array {
		$id  = $product->get_image_id();
		$src = $id ? (string) wp_get_attachment_image_url( (int) $id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
		return [
			'src' => $src,
			'alt' => $product->get_name(),
		];
	}

	/**
	 * A variable parent saved without a chosen variation needs "Select options"
	 * on the product page before it can be carted (§16.1).
	 */
	private function needs_selection( \WC_Product $product, Item $item ): bool {
		if ( $item->variation_id > 0 ) {
			return false;
		}
		return $product->is_type( 'variable' ) || $product->is_type( 'grouped' );
	}
}
