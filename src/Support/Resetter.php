<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Support;

use Flexa\Wishlist\Install\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Wipes all Flexa Wishlist data. Shared by the Settings danger zone (REST) and
 * the `wp flexa-wishlist reset` CLI command so the two paths can never drift.
 * Fires `flexa_wishlist/data_reset` for downstream cleanup.
 */
final class Resetter {
	/**
	 * @return array{settings_removed:bool, tables_dropped:bool}
	 */
	public static function reset_all(): array {
		$removed = delete_option( Settings::OPTION_KEY );

		Migrator::drop();

		// Clear any per-owner count caches (transients) we may have set.
		self::flush_count_caches();

		do_action( 'flexa_wishlist/data_reset' );

		return [
			'settings_removed' => (bool) $removed,
			'tables_dropped'   => true,
		];
	}

	private static function flush_count_caches(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$like    = $wpdb->esc_like( '_transient_flexa_wl_count_' ) . '%';
		$to_like = $wpdb->esc_like( '_transient_timeout_flexa_wl_count_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $to_like ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	}
}
