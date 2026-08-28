<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Domain\Guest\GuestSession;
use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Base for every REST controller. Provides the {success,message,data} envelope,
 * the shared permission callbacks, and storefront owner resolution. Every route
 * declares a real permission_callback (§21) — the admin ones gate on caps, the
 * storefront mutating ones verify the REST nonce as a CSRF guard.
 */
abstract class BaseRestController {
	public const NAMESPACE = FLEXA_WISHLIST_REST_NAMESPACE;

	abstract public function register_routes(): void;

	// --- Envelope -----------------------------------------------------------

	/**
	 * @param array<string,mixed> $data
	 */
	protected function success( array $data = [], string $message = '', int $status = 200 ): WP_REST_Response {
		$payload = [ 'success' => true ];
		if ( '' !== $message ) {
			$payload['message'] = $message;
		}
		$payload['data'] = $data;

		$response = new WP_REST_Response( $payload );
		$response->set_status( $status );
		return $response;
	}

	protected function fail( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( 'flexa_wishlist_' . $code, $message, [ 'status' => $status ] );
	}

	// --- Storefront permissions --------------------------------------------

	/** Reads are public (respect visibility inside the handler). */
	public function public_permission( WP_REST_Request $request ): bool {
		unset( $request );
		// reason: intentionally public. These routes only READ data that is
		// already public (a shared wishlist's own items, storefront state);
		// per-item/per-list visibility is enforced inside each handler, not at
		// the permission layer. No user data is exposed or mutated here, so a
		// nonce/capability gate would add nothing. Not equivalent to
		// __return_true on a write or private-data route.
		return true;
	}

	/** Mutations require a valid REST nonce (CSRF guard for guests + users). */
	public function storefront_write_permission( WP_REST_Request $request ): bool|WP_Error {
		$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return $this->fail( 'bad_nonce', __( 'Your session expired. Please refresh and try again.', 'flexa-wishlist-for-woocommerce' ), 403 );
		}
		return true;
	}

	// --- Admin permissions --------------------------------------------------

	public function manage_permission( WP_REST_Request $request ): bool|WP_Error {
		unset( $request );
		if ( ! Capabilities::can_manage() ) {
			return $this->fail( 'forbidden', __( 'You do not have permission to view this.', 'flexa-wishlist-for-woocommerce' ), rest_authorization_required_code() );
		}
		return true;
	}

	public function settings_permission( WP_REST_Request $request ): bool|WP_Error {
		unset( $request );
		if ( ! Capabilities::can_manage_settings() ) {
			return $this->fail( 'forbidden', __( 'You do not have permission to change settings.', 'flexa-wishlist-for-woocommerce' ), rest_authorization_required_code() );
		}
		return true;
	}

	// --- Owner resolution ---------------------------------------------------

	protected function owner_read(): OwnerContext {
		return GuestSession::instance()->current_owner();
	}

	protected function owner_write(): OwnerContext {
		return GuestSession::instance()->ensure_owner_for_write();
	}
}
