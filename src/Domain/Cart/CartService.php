<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Cart;

use Flexa\Wishlist\Domain\Item\Item;
use Flexa\Wishlist\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The cart bridge (§14): moves saved items into the WooCommerce cart using Woo's
 * own add-to-cart pipeline so pricing, stock, coupons and third-party cart logic
 * all keep working. Every item is re-validated live at add time — a saved product
 * may since have gone out of stock, become non-purchasable, or vanished — and a
 * machine-readable skip reason is returned so the storefront can explain why an
 * item could not be carted (§14.3). Never trusts the snapshot for purchasability.
 */
final class CartService {
	public const OK              = 'ok';
	public const UNAVAILABLE     = 'unavailable';
	public const OUT_OF_STOCK    = 'out_of_stock';
	public const NEEDS_SELECTION = 'needs_selection';
	public const ADD_FAILED      = 'add_failed';

	/**
	 * Add one saved item to the cart.
	 *
	 * @return array{added:bool,reason:string,productId:int,name:string}
	 */
	public function add_item( Item $item ): array {
		$product_id   = $item->product_id;
		$variation_id = $item->variation_id;
		$product      = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

		if ( ! $product instanceof \WC_Product ) {
			return $this->result( false, self::UNAVAILABLE, $product_id, '' );
		}

		$name = $product->get_name();

		// A variable/grouped parent saved without a chosen variation must be
		// configured on the product page first — it cannot be carted directly.
		if ( 0 === $variation_id && ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) ) {
			return $this->result( false, self::NEEDS_SELECTION, $product_id, $name );
		}

		// External/affiliate products have no local cart entry.
		if ( $product->is_type( 'external' ) ) {
			return $this->result( false, self::UNAVAILABLE, $product_id, $name );
		}

		if ( ! $product->is_in_stock() ) {
			return $this->result( false, self::OUT_OF_STOCK, $product_id, $name );
		}

		if ( ! $product->is_purchasable() ) {
			return $this->result( false, self::UNAVAILABLE, $product_id, $name );
		}

		$cart = $this->cart();
		if ( ! $cart instanceof \WC_Cart ) {
			return $this->result( false, self::ADD_FAILED, $product_id, $name );
		}

		$added = $cart->add_to_cart( $product_id, max( 1, $item->quantity ), $variation_id );
		if ( false === $added ) {
			return $this->result( false, self::ADD_FAILED, $product_id, $name );
		}

		do_action( 'flexa_wishlist/cart/item_added', $item, $product );

		return $this->result( true, self::OK, $product_id, $name );
	}

	/**
	 * Add many items, collecting a per-item outcome. The caller decides what to do
	 * with the skipped set and (per the remove-after-add setting) the added ids.
	 *
	 * @param Item[] $items
	 * @return array{added:int,addedItemIds:list<int>,results:list<array{itemId:int,productId:int,name:string,added:bool,reason:string}>}
	 */
	public function add_items( array $items ): array {
		$results        = [];
		$added_item_ids = [];
		$added          = 0;

		foreach ( $items as $item ) {
			$r         = $this->add_item( $item );
			$results[] = [
				'itemId'    => $item->id,
				'productId' => $r['productId'],
				'name'      => $r['name'],
				'added'     => $r['added'],
				'reason'    => $r['reason'],
			];
			if ( $r['added'] ) {
				++$added;
				$added_item_ids[] = $item->id;
			}
		}

		return [
			'added'        => $added,
			'addedItemIds' => $added_item_ids,
			'results'      => $results,
		];
	}

	/** Whether a successfully-carted item should leave the wishlist (§14.4). */
	public function remove_after_add(): bool {
		return (bool) Settings::get( 'advanced', 'remove_after_add_to_cart' );
	}

	public function cart_count(): int {
		$cart = $this->cart();
		return $cart instanceof \WC_Cart ? (int) $cart->get_cart_contents_count() : 0;
	}

	public function cart_url(): string {
		return function_exists( 'wc_get_cart_url' ) ? (string) wc_get_cart_url() : '';
	}

	/**
	 * The live cart, loading the session on demand (REST requests do not boot the
	 * cart the way a normal front-end request does).
	 */
	private function cart(): ?\WC_Cart {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$wc = WC();
		if ( ! $wc->cart instanceof \WC_Cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}

		return $wc->cart instanceof \WC_Cart ? $wc->cart : null;
	}

	/**
	 * @return array{added:bool,reason:string,productId:int,name:string}
	 */
	private function result( bool $added, string $reason, int $product_id, string $name ): array {
		return [
			'added'     => $added,
			'reason'    => $reason,
			'productId' => $product_id,
			'name'      => $name,
		];
	}
}
