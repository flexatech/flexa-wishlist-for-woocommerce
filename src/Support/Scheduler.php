<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Support;

use Flexa\Wishlist\Install\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin's daily maintenance jobs (§17.7): guest wishlist cleanup past
 * the retention window.
 */
final class Scheduler {
	use SingletonTrait;

	public const GUEST_CLEANUP = 'flexa_wishlist/cron/guest_cleanup';

	public function register(): void {
		add_action( 'init', [ $this, 'ensure_scheduled' ] );
		add_action( self::GUEST_CLEANUP, [ $this, 'run_guest_cleanup' ] );
	}

	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::GUEST_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::GUEST_CLEANUP );
		}
	}

	/**
	 * Hard-delete guest wishlists (and their items) whose most recent activity
	 * is older than the retention window. Account wishlists are never touched.
	 */
	public function run_guest_cleanup(): void {
		global $wpdb;

		$days   = (int) Settings::get( 'general', 'retention_days' );
		$days   = $days > 0 ? $days : 30;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$lists = $wpdb->prefix . Migrator::LIST_TABLE;
		$items = $wpdb->prefix . Migrator::ITEM_TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders
		$expired = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE guest_token_hash IS NOT NULL AND updated_at < %s',
				$lists,
				$cutoff
			)
		);
		$expired = array_map( 'intval', (array) $expired );
		if ( [] === $expired ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $expired ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE list_id IN ({$placeholders})",
				array_merge( [ $items ], $expired )
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE id IN ({$placeholders})",
				array_merge( [ $lists ], $expired )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

		do_action( 'flexa_wishlist/guest/cleanup', count( $expired ) );
	}
}
