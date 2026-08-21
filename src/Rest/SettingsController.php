<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Rest;

use Flexa\Wishlist\Support\Resetter;
use Flexa\Wishlist\Support\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * GET/POST /settings — read or persist the plugin settings option.
 * POST /settings/reset — danger-zone wipe via the shared Resetter.
 *
 * Writes are partial-merged over the stored option (a one-toggle save never
 * wipes the rest) and re-sanitized against the schema in {@see Settings}.
 */
final class SettingsController extends BaseRestController {
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'settings_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'settings_permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/reset',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset' ],
				'permission_callback' => [ $this, 'settings_permission' ],
			]
		);
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		return $this->success( [ 'settings' => Settings::all() ] );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$incoming = (array) $request->get_json_params();

		$old = Settings::all();
		$new = Settings::sanitize_merge( $incoming );
		update_option( Settings::OPTION_KEY, $new );

		do_action( 'flexa_wishlist/settings/updated', $new, $old );

		return $this->success( [ 'settings' => $new ], __( 'Settings saved.', 'flexa-woocommerce-wishlist' ) );
	}

	public function reset( WP_REST_Request $request ): WP_REST_Response {
		unset( $request );
		$result = Resetter::reset_all();
		return $this->success( $result, __( 'All wishlist data was removed.', 'flexa-woocommerce-wishlist' ) );
	}
}
