<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Deactivation is non-destructive: wishlists and settings survive so a
 * reactivation restores the exact prior state. Only scheduled jobs are cleared
 * (they are re-scheduled on the next boot). Data removal happens only via the
 * danger-zone Resetter or uninstall.
 */
final class Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'flexa_wishlist/cron/guest_cleanup' );

		flush_rewrite_rules();
	}
}
