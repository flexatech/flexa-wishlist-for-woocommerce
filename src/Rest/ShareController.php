<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Product\ProductHydrator;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Domain\WishlistService;
use Flexa\Wishlist\Integration\ShareRoute;
use Flexa\Wishlist\Support\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Sharing (§20). MVP covers the default list in Free.
 * - POST /lists/{id}/share      create or regenerate the unguessable slug
 * - GET  /shared/{slug}         public read-only view (respects visibility)
 * - POST /shared/{slug}/save    save-all into the caller's own default list
 */
final class ShareController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/lists/(?P<id>\d+)/share',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_link' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/shared/(?P<slug>[A-Za-z0-9]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'view' ],
				'permission_callback' => [ $this, 'public_permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/shared/(?P<slug>[A-Za-z0-9]+)/save',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_all' ],
				'permission_callback' => [ $this, 'storefront_write_permission' ],
			]
		);
	}

	public function create_link( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! (bool) Settings::get( 'sharing', 'enabled' ) ) {
			return $this->fail( 'sharing_disabled', __( 'Sharing is disabled.', 'flexa-wishlist-for-woocommerce' ), 403 );
		}

		$owner = $this->owner_write();
		$lists = new WishlistRepository();
		$list  = $lists->find( (int) $request->get_param( 'id' ) );
		if ( ! $list instanceof Wishlist || ! $lists->owns( $list, $owner ) ) {
			return $this->fail( 'not_found', __( 'Wishlist not found.', 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$slug = $this->unique_slug( $lists );
		$lists->update(
			$list->id,
			[
				'share_slug' => $slug,
				'visibility' => 'shared',
			]
		);
		do_action( 'flexa_wishlist/list/shared', $list->id, $slug );

		return $this->success(
			[
				'slug' => $slug,
				'url'  => ShareRoute::url_for( $slug ),
			],
			__( 'Share link ready.', 'flexa-wishlist-for-woocommerce' )
		);
	}

	public function view( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug  = (string) $request->get_param( 'slug' );
		$lists = new WishlistRepository();
		$list  = $lists->find_by_slug( $slug );

		// Private (or missing) lists return a neutral not-available — never
		// disclose existence (§18.5).
		if ( ! $list instanceof Wishlist || 'private' === $list->visibility ) {
			return $this->fail( 'unavailable', __( "This wishlist isn't available.", 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$service = new WishlistService();
		$items   = $service->items()->for_list( $list->id, 1, 200 );

		return $this->success(
			[
				'list'  => [
					'name'      => $list->name,
					'ownerName' => $this->owner_display_name( $list ),
					'count'     => $service->items()->count_for_list( $list->id ),
				],
				'items' => ( new ProductHydrator() )->hydrate_many( $items ),
			]
		);
	}

	public function save_all( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug  = (string) $request->get_param( 'slug' );
		$lists = new WishlistRepository();
		$list  = $lists->find_by_slug( $slug );
		if ( ! $list instanceof Wishlist || 'private' === $list->visibility ) {
			return $this->fail( 'unavailable', __( "This wishlist isn't available.", 'flexa-wishlist-for-woocommerce' ), 404 );
		}

		$owner   = $this->owner_write();
		$service = new WishlistService();
		$target  = $service->resolve_target_list( $owner );
		if ( null === $target ) {
			return $this->fail( 'save_failed', __( 'Could not save these items.', 'flexa-wishlist-for-woocommerce' ), 400 );
		}

		$added = 0;
		foreach ( $service->items()->raw_rows_for_list( $list->id ) as $row ) {
			$before = $service->items()->find_id( $target->id, (int) $row['product_id'], (int) $row['variation_id'] );
			$service->items()->restore(
				$target->id,
				[
					'product_id'         => (int) $row['product_id'],
					'variation_id'       => (int) $row['variation_id'],
					'attributes_summary' => (string) $row['attributes_summary'],
					'quantity'           => (int) $row['quantity'],
					'price_amount'       => $row['price_amount'] ?? null,
					'price_currency'     => (string) $row['price_currency'],
				]
			);
			if ( null === $before ) {
				++$added;
			}
		}

		return $this->success(
			[
				'added' => $added,
				'state' => $service->state( $owner ),
			],
			/* translators: %d: number of items added. */
			sprintf( _n( '%d item saved to your wishlist.', '%d items saved to your wishlist.', $added, 'flexa-wishlist-for-woocommerce' ), $added )
		);
	}

	private function unique_slug( WishlistRepository $lists ): string {
		do {
			$slug = strtolower( wp_generate_password( 24, false ) );
			$slug = preg_replace( '/[^a-z0-9]/', '', $slug ) ?? '';
			$slug = substr( $slug . strtolower( wp_generate_password( 8, false ) ), 0, 22 );
		} while ( null !== $lists->find_by_slug( $slug ) );

		return $slug;
	}

	private function owner_display_name( Wishlist $list ): string {
		if ( ! (bool) Settings::get( 'sharing', 'show_owner_name' ) ) {
			return __( 'A customer', 'flexa-wishlist-for-woocommerce' );
		}
		if ( $list->owner_user_id > 0 ) {
			$user = get_userdata( $list->owner_user_id );
			if ( $user instanceof \WP_User && '' !== $user->display_name ) {
				return $user->display_name;
			}
		}
		return __( 'A customer', 'flexa-wishlist-for-woocommerce' );
	}
}
