<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Product\ProductHydrator;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Domain\WishlistService;
use Flexa\Wishlist\Support\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Wishlist (list) endpoints.
 * - GET  /lists            owner's list summaries
 * - GET  /lists/{id}       one list's hydrated, paginated items
 * - POST /lists            create a list
 * - PATCH/DELETE /lists/{id}  rename / delete a list
 *
 * Every mutating route validates ownership server-side; ids are never trusted.
 */
final class ListsController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lists',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'public_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'storefront_write_permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/lists/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'show' ],
					'permission_callback' => [ $this, 'public_permission' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'storefront_write_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'destroy' ],
					'permission_callback' => [ $this, 'storefront_write_permission' ],
				],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$owner = $this->owner_read();
		return $this->success( [ 'lists' => ( new WishlistService() )->state( $owner )['lists'] ] );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner   = $this->owner_read();
		$service = new WishlistService();
		$lists   = $service->lists();

		$list = $lists->find( (int) $request->get_param( 'id' ) );
		if ( ! $list instanceof Wishlist || ! $lists->owns( $list, $owner ) ) {
			return $this->fail( 'not_found', __( 'Wishlist not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$per_page = (int) Settings::get( 'page', 'per_page' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$items       = $service->items()->for_list( $list->id, $page, $per_page );
		$total       = $service->items()->count_for_list( $list->id );
		$list->count = $total;

		return $this->success(
			[
				'list'       => $list->to_array(),
				'items'      => ( new ProductHydrator() )->hydrate_many( $items ),
				'pagination' => [
					'page'    => $page,
					'perPage' => $per_page,
					'total'   => $total,
					'hasMore' => ( $page * $per_page ) < $total,
				],
			]
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_write();
		$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return $this->fail( 'invalid_name', __( 'Please give the list a name.', 'flexa-wishlist-for-woocommerce' ), 400 );
		}

		$lists = new WishlistRepository();
		// Ensure a default exists first so the new list is non-default.
		$lists->default_for_owner( $owner, true, (string) Settings::get( 'general', 'default_list_name' ) );
		$id = $lists->create(
			[
				'owner_user_id'    => $owner->user_id,
				'guest_token_hash' => $owner->guest_hash,
				'name'             => $name,
			]
		);

		$list = $lists->find( $id );
		return $this->success( [ 'list' => $list?->to_array() ], __( 'List created.', 'flexa-wishlist-for-woocommerce' ) );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_write();
		$lists = new WishlistRepository();
		$list  = $lists->find( (int) $request->get_param( 'id' ) );
		if ( ! $list instanceof Wishlist || ! $lists->owns( $list, $owner ) ) {
			return $this->fail( 'not_found', __( 'Wishlist not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$body    = (array) $request->get_json_params();
		$changes = [];
		if ( array_key_exists( 'name', $body ) ) {
			$changes['name'] = sanitize_text_field( (string) $body['name'] );
		}
		if ( array_key_exists( 'visibility', $body ) && in_array( (string) $body['visibility'], Settings::VISIBILITIES, true ) ) {
			$changes['visibility'] = (string) $body['visibility'];
		}
		if ( [] !== $changes ) {
			$lists->update( $list->id, $changes );
			if ( isset( $changes['visibility'] ) ) {
				do_action( 'flexa_wishlist/list/visibility_changed', $list->id, $changes['visibility'] );
			}
		}

		return $this->success( [ 'list' => $lists->find( $list->id )?->to_array() ] );
	}

	public function destroy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$owner = $this->owner_write();
		$lists = new WishlistRepository();
		$list  = $lists->find( (int) $request->get_param( 'id' ) );
		if ( ! $list instanceof Wishlist || ! $lists->owns( $list, $owner ) ) {
			return $this->fail( 'not_found', __( 'Wishlist not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
		}
		if ( $list->is_default ) {
			return $this->fail( 'default_protected', __( 'The default list cannot be deleted.', 'flexa-wishlist-for-woocommerce' ), 400 );
		}

		$service = new WishlistService();
		$move    = (bool) $request->get_param( 'moveItems' );
		if ( $move ) {
			$default = $service->resolve_target_list( $owner );
			if ( null !== $default ) {
				foreach ( $service->items()->raw_rows_for_list( $list->id ) as $row ) {
					$service->items()->restore( $default->id, $row );
				}
			}
		}
		$service->items()->delete_for_list( $list->id );
		$lists->delete( $list->id );

		return $this->success( [ 'state' => $service->state( $owner ) ], __( 'List deleted.', 'flexa-wishlist-for-woocommerce' ) );
	}
}
