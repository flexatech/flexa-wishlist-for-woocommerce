<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\WishlistService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET /state — the single cache-safe hydration call (§17.1). Returns the owner's
 * count, saved item keys, and list summaries. Public + owner-scoped: it reflects
 * only the current visitor's own wishlist and never leaks cross-owner data.
 */
final class StateController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/state',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'public_permission' ],
			]
		);
	}

	public function get_state( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$state = ( new WishlistService() )->state( $this->owner_read() );

		// Never cache per-owner state at the HTTP layer.
		$response = $this->success( $state );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}
}
