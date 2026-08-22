<?php
/**
 * Uninstall handler. Honors the "Delete data on uninstall" setting (§18.3):
 * when off (default), only the DB-version marker and transient count caches are
 * cleared and the user's wishlists survive a reinstall; when on, every option
 * and custom table is removed.
 *
 * Runs in isolation (the plugin is NOT bootstrapped during uninstall), so the
 * table names and option keys below are deliberately duplicated from
 * Flexa\Wishlist\Install\Migrator and Support\Settings. Keep them in sync.
 *
 * @package Flexa\Wishlist
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$flexa_wl_settings = get_option( 'flexa_wishlist_settings' );
$flexa_wl_purge    = is_array( $flexa_wl_settings )
	&& ! empty( $flexa_wl_settings['advanced']['delete_data_on_uninstall'] );

if ( $flexa_wl_purge ) {
	global $wpdb;

	delete_option( 'flexa_wishlist_settings' );
	delete_option( 'flexa_wishlist_db_version' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	foreach ( [ 'flexa_wl_items', 'flexa_wl_lists', 'flexa_wl_stock_subs', 'flexa_wl_analytics' ] as $flexa_wl_table ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $flexa_wl_table ) );
	}

	$flexa_wl_like    = $wpdb->esc_like( '_transient_flexa_wl_count_' ) . '%';
	$flexa_wl_to_like = $wpdb->esc_like( '_transient_timeout_flexa_wl_count_' ) . '%';
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $flexa_wl_like, $flexa_wl_to_like ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
}
