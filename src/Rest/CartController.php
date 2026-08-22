<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Cart\CartService;
use Flexa\Wishlist\Domain\Item\Item;
use Flexa\Wishlist\Domain\Item\ItemRepository;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Domain\WishlistService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * The cart bridge REST surface (§14). Moves saved items into the WooCommerce cart
 * server-side (Woo pipeline → coupons/pricing/stock all honoured) and reports
 * per-item skip reasons. Ownership is validated on every route; the setting
 * `advanced.remove_after_add_to_cart` decides whether a carted item leaves the
 * list. Both routes return the fresh wishlist state so buttons/counters reconcile.
 *
 * - POST /cart/add       add one owned item (by itemId) to the cart
 * - POST /cart/add-all   add every carriable item of a list, with a skip report
 */
final class CartController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/cart/add',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
				'args'                => [
					'itemId' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/cart/add-all',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_all' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
				'args'                => [
					'listId' => [
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function add( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_read();
		$items = new ItemRepository();
		$lists = new WishlistRepository();

		$item = $items->find( (int) $request->get_param( 'itemId' ) );
		if ( ! $item instanceof Item || ! $items->owned_by( $item, $lists->ids_for_owner( $owner ) ) ) {
			return $this->fail( 'not_found', __( 'Item not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$cart    = new CartService();
		$outcome = $cart->add_item( $item );

		if ( ! $outcome['added'] ) {
			return $this->fail( 'cart_add_failed', $this->reason_message( $outcome['reason'] ), 409 );
		}

		$removed = false;
		if ( $cart->remove_after_add() ) {
			$items->delete( $item->id );
			$removed = true;
		}

		do_action( 'flexa_wishlist/cart/added', $item, $owner );

		return $this->success(
			[
				'added'     => true,
				'removed'   => $removed,
				'cartCount' => $cart->cart_count(),
				'cartUrl'   => $cart->cart_url(),
				'state'     => ( new WishlistService() )->state( $owner ),
			],
			__( 'Added to cart.', 'flexa-wishlist-for-woocommerce' )
		);
	}

	public function add_all( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner   = $this->owner_write();
		$service = new WishlistService();
		$lists   = $service->lists();
		$list_id = (int) $request->get_param( 'listId' );

		if ( $list_id > 0 ) {
			$list = $lists->find( $list_id );
			if ( ! $list instanceof Wishlist || ! $lists->owns( $list, $owner ) ) {
				return $this->fail( 'not_found', __( 'Wishlist not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
			}
		} else {
			$list = $lists->default_for_owner( $owner );
		}

		if ( ! $list instanceof Wishlist ) {
			return $this->success(
				[
					'added'     => 0,
					'skipped'   => [],
					'cartCount' => ( new CartService() )->cart_count(),
					'cartUrl'   => ( new CartService() )->cart_url(),
					'state'     => $service->state( $owner ),
				],
				__( 'Your wishlist is empty.', 'flexa-wishlist-for-woocommerce' )
			);
		}

		// A wishlist page is capped well below this; pull the whole list in one go.
		$all_items = $service->items()->for_list( $list->id, 1, 500 );

		$cart    = new CartService();
		$outcome = $cart->add_items( $all_items );

		if ( $cart->remove_after_add() && [] !== $outcome['addedItemIds'] ) {
			foreach ( $outcome['addedItemIds'] as $item_id ) {
				$service->items()->delete( (int) $item_id );
			}
		}

		$skipped = [];
		foreach ( $outcome['results'] as $r ) {
			if ( $r['added'] ) {
				continue;
			}
			$skipped[] = [
				'itemId'    => $r['itemId'],
				'productId' => $r['productId'],
				'name'      => $r['name'],
				'reason'    => $r['reason'],
				'message'   => $this->reason_message( $r['reason'] ),
			];
		}

		do_action( 'flexa_wishlist/cart/added_all', $list, $outcome, $owner );

		$added = $outcome['added'];
		if ( $added > 0 ) {
			/* translators: %d: number of products added to the cart. */
			$message = sprintf( _n( 'Added %d item to your cart.', 'Added %d items to your cart.', $added, 'flexa-wishlist-for-woocommerce' ), $added );
		} else {
			$message = __( 'No items could be added to the cart.', 'flexa-wishlist-for-woocommerce' );
		}

		return $this->success(
			[
				'added'     => $added,
				'skipped'   => $skipped,
				'cartCount' => $cart->cart_count(),
				'cartUrl'   => $cart->cart_url(),
				'state'     => $service->state( $owner ),
			],
			$message
		);
	}

	/**
	 * Human-readable copy for a CartService skip reason code.
	 */
	private function reason_message( string $reason ): string {
		switch ( $reason ) {
			case CartService::OUT_OF_STOCK:
				return __( 'This item is out of stock.', 'flexa-wishlist-for-woocommerce' );
			case CartService::NEEDS_SELECTION:
				return __( 'Choose the options for this item on its product page first.', 'flexa-wishlist-for-woocommerce' );
			case CartService::UNAVAILABLE:
				return __( 'This item is no longer available.', 'flexa-wishlist-for-woocommerce' );
			default:
				return __( 'This item could not be added to the cart.', 'flexa-wishlist-for-woocommerce' );
		}
	}
}
