<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Analytics\DashboardRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Admin read endpoints (capability: manage_woocommerce). Free ships the basic
 * dashboard (counts + top 5). Richer analytics is a Pro surface that hooks the
 * same namespace via `flexa_wishlist/rest/register_routes`.
 */
final class AdminController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/admin/dashboard',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'dashboard' ],
				'permission_callback' => [ $this, 'manage_permission' ],
			]
		);
	}

	public function dashboard( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$repo = new DashboardRepository();

		return $this->success(
			[
				'totals'      => $repo->totals(),
				'topProducts' => $repo->top_products( 5 ),
				'proEnabled'  => (bool) apply_filters( 'flexa_wishlist/pro/is_licensed', false ),
			]
		);
	}
}
