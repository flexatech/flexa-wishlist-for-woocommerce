<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Capability helpers. Per §0/§21: viewing the admin (dashboard, wishlist
 * browser) requires `manage_woocommerce`; changing settings and the danger-zone
 * reset require `manage_options`. Each gate is filterable so a host site can
 * re-scope access.
 */
final class Capabilities {
	public const MANAGE   = 'manage_woocommerce';
	public const SETTINGS = 'manage_options';

	public static function can_manage(): bool {
		return current_user_can( apply_filters( 'flexa_wishlist/capabilities/manage', self::MANAGE ) );
	}

	public static function can_manage_settings(): bool {
		return current_user_can( apply_filters( 'flexa_wishlist/capabilities/settings', self::SETTINGS ) );
	}
}
