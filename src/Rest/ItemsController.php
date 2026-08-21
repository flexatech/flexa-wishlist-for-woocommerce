<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Item\Item;
use Flexa\Wishlist\Domain\Item\ItemRepository;
use Flexa\Wishlist\Domain\Product\ProductHydrator;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Domain\WishlistService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Item mutations — the heart of the storefront toggle (§9.2). Every mutating
 * route validates ownership server-side; ids are never trusted (§21).
 *
 * - POST   /items            add product/variation (idempotent) to a list
 * - POST   /items/toggle     save if absent / remove if present, by product key
 * - DELETE /items/{id}       remove by id (returns a restore row for Undo)
 * - POST   /items/restore    re-insert a removed row, preserving date + position
 * - PATCH  /items/{id}       update quantity (Pro surface)
 */
final class ItemsController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/items',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
				'args'                => $this->add_args(),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/items/toggle',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'toggle' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
				'args'                => $this->add_args(),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/items/restore',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/items/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove' ],
					'permission_callback' => [ $this, 'storefront_write_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'storefront_write_permission' ],
				],
			]
		);
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function add_args(): array {
		return [
			'productId'   => [
				'required'          => true,
				'sanitize_callback' => 'absint',
			],
			'variationId' => [
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
			'quantity'    => [
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'listId'      => [
				'default'           => 0,
				'sanitize_callback' => 'absint',
			],
		];
	}

	public function add( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner   = $this->owner_write();
		$service = new WishlistService();

		$result = $service->add(
			$owner,
			(int) $request->get_param( 'productId' ),
			(int) $request->get_param( 'variationId' ),
			(int) $request->get_param( 'quantity' ),
			(int) $request->get_param( 'listId' )
		);

		if ( null === $result ) {
			return $this->fail( 'add_failed', __( 'That product could not be saved.', 'flexa-woocommerce-wishlist' ), 400 );
		}

		return $this->success(
			[
				'item'  => ( new ProductHydrator() )->hydrate( $result['item'] ),
				'state' => $service->state( $owner ),
			],
			$result['created'] ? __( 'Saved to your wishlist.', 'flexa-woocommerce-wishlist' ) : ''
		);
	}

	public function toggle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner        = $this->owner_write();
		$service      = new WishlistService();
		$product_id   = (int) $request->get_param( 'productId' );
		$variation_id = (int) $request->get_param( 'variationId' );
		$list_id      = (int) $request->get_param( 'listId' );

		$lists = $service->lists();
		$items = $service->items();

		// Look for the key anywhere the owner owns (or in the given list).
		$target_ids = $list_id > 0 ? [ $list_id ] : $lists->ids_for_owner( $owner );
		$found      = null;
		foreach ( $target_ids as $lid ) {
			$id = $items->find_id( (int) $lid, $product_id, $variation_id );
			if ( null !== $id ) {
				$found = $id;
				break;
			}
		}

		if ( null !== $found ) {
			$item = $items->find( $found );
			$items->delete( $found );
			if ( $item instanceof Item ) {
				do_action( 'flexa_wishlist/item/removed', $item, $owner );
			}
			return $this->success(
				[
					'saved' => false,
					'state' => $service->state( $owner ),
				]
			);
		}

		$result = $service->add( $owner, $product_id, $variation_id, (int) $request->get_param( 'quantity' ), $list_id );
		if ( null === $result ) {
			return $this->fail( 'toggle_failed', __( 'That product could not be saved.', 'flexa-woocommerce-wishlist' ), 400 );
		}

		return $this->success(
			[
				'saved' => true,
				'item'  => ( new ProductHydrator() )->hydrate( $result['item'] ),
				'list'  => $result['list']->to_array(),
				'state' => $service->state( $owner ),
			],
			__( 'Saved to your wishlist.', 'flexa-woocommerce-wishlist' )
		);
	}

	public function remove( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_read();
		$items = new ItemRepository();
		$lists = new WishlistRepository();

		$item = $items->find( (int) $request->get_param( 'id' ) );
		if ( ! $item instanceof Item || ! $items->owned_by( $item, $lists->ids_for_owner( $owner ) ) ) {
			return $this->fail( 'not_found', __( 'Item not found.', 'flexa-woocommerce-wishlist' ), 404 );
		}

		$restore_row = [
			'listId'        => $item->list_id,
			'productId'     => $item->product_id,
			'variationId'   => $item->variation_id,
			'attributes'    => $item->attributes_summary,
			'quantity'      => $item->quantity,
			'priceAmount'   => $item->price_amount,
			'priceCurrency' => $item->price_currency,
			'position'      => $item->position,
			'dateAdded'     => $item->date_added,
		];

		$items->delete( $item->id );
		do_action( 'flexa_wishlist/item/removed', $item, $owner );

		return $this->success(
			[
				'restore' => $restore_row,
				'state'   => ( new WishlistService() )->state( $owner ),
			],
			__( 'Removed from your wishlist.', 'flexa-woocommerce-wishlist' )
		);
	}

	public function restore( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_write();
		$body  = (array) $request->get_json_params();
		$lists = new WishlistRepository();
		$items = new ItemRepository();

		$list_id = (int) ( $body['listId'] ?? 0 );
		$list    = $lists->find( $list_id );
		if ( null === $list || ! $lists->owns( $list, $owner ) ) {
			// The original list is gone; fall back to the owner's default.
			$list = ( new WishlistService() )->resolve_target_list( $owner );
			if ( null === $list ) {
				return $this->fail( 'restore_failed', __( 'Could not restore the item.', 'flexa-woocommerce-wishlist' ), 400 );
			}
		}

		$items->restore(
			$list->id,
			[
				'product_id'         => (int) ( $body['productId'] ?? 0 ),
				'variation_id'       => (int) ( $body['variationId'] ?? 0 ),
				'attributes_summary' => (string) ( $body['attributes'] ?? '' ),
				'quantity'           => (int) ( $body['quantity'] ?? 1 ),
				'price_amount'       => $body['priceAmount'] ?? null,
				'price_currency'     => (string) ( $body['priceCurrency'] ?? '' ),
				'position'           => (int) ( $body['position'] ?? 0 ),
				'date_added'         => (string) ( $body['dateAdded'] ?? current_time( 'mysql', true ) ),
			]
		);

		return $this->success( [ 'state' => ( new WishlistService() )->state( $owner ) ] );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_read();
		$items = new ItemRepository();
		$lists = new WishlistRepository();

		$item = $items->find( (int) $request->get_param( 'id' ) );
		if ( ! $item instanceof Item || ! $items->owned_by( $item, $lists->ids_for_owner( $owner ) ) ) {
			return $this->fail( 'not_found', __( 'Item not found.', 'flexa-woocommerce-wishlist' ), 404 );
		}

		$body = (array) $request->get_json_params();
		if ( array_key_exists( 'quantity', $body ) ) {
			$items->update( $item->id, [ 'quantity' => (int) $body['quantity'] ] );
		}

		return $this->success( [ 'state' => ( new WishlistService() )->state( $owner ) ] );
	}
}
