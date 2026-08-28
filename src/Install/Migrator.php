<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Install;

defined( 'ABSPATH' ) || exit;

/**
 * Versioned schema migrator. Runs on activation and on admin_init via
 * maybe_upgrade(). Owns the two custom tables: lists and items.
 */
final class Migrator {
	public const DB_VERSION     = '1.0.0';
	public const VERSION_OPTION = 'flexa_wishlist_db_version';

	public const LIST_TABLE = 'flexa_wl_lists';
	public const ITEM_TABLE = 'flexa_wl_items';

	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '' );
		if ( self::DB_VERSION === $installed ) {
			return;
		}
		self::migrate();
	}

	public static function migrate(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$lists           = $wpdb->prefix . self::LIST_TABLE;
		$items           = $wpdb->prefix . self::ITEM_TABLE;

		// A wishlist is owned by exactly one of: a user (owner_user_id > 0) or a
		// guest (guest_token_hash set). share_slug is unique when present.
		$lists_sql = "CREATE TABLE {$lists} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			guest_token_hash CHAR(64) DEFAULT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			is_default TINYINT(1) NOT NULL DEFAULT 0,
			visibility VARCHAR(20) NOT NULL DEFAULT 'private',
			share_slug VARCHAR(64) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY owner_idx (owner_user_id),
			KEY guest_idx (guest_token_hash),
			UNIQUE KEY share_slug_idx (share_slug)
		) {$charset_collate};";

		// One row per saved product/variation in a list. Uniqueness is enforced
		// per (list, product, variation) so a re-add is idempotent (§9.9).
		$items_sql = "CREATE TABLE {$items} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			list_id BIGINT UNSIGNED NOT NULL,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			attributes_summary VARCHAR(500) NOT NULL DEFAULT '',
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			price_amount DECIMAL(19,4) DEFAULT NULL,
			price_currency VARCHAR(10) NOT NULL DEFAULT '',
			position INT NOT NULL DEFAULT 0,
			date_added DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY list_product_variation_idx (list_id, product_id, variation_id),
			KEY list_pos_idx (list_id, position),
			KEY product_idx (product_id)
		) {$charset_collate};";

		dbDelta( $lists_sql );
		dbDelta( $items_sql );

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Drop every custom table. Used by the shared Resetter and by uninstall
	 * (when the "delete data on uninstall" setting is on).
	 */
	public static function drop(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		foreach ( [ self::ITEM_TABLE, self::LIST_TABLE ] as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		delete_option( self::VERSION_OPTION );
	}
}
