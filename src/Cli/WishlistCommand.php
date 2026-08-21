<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Cli;

use Flexa\Wishlist\Domain\Analytics\DashboardRepository;
use Flexa\Wishlist\Support\Resetter;
use Flexa\Wishlist\Support\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for Flexa Wishlist. The reset command shares the exact
 * Resetter path used by the admin danger zone so the two can never drift (§24 I3).
 */
final class WishlistCommand {
	public static function register(): void {
		if ( ! class_exists( \WP_CLI::class ) ) {
			return;
		}
		\WP_CLI::add_command( 'flexa-wishlist', self::class );
	}

	/**
	 * Remove all wishlist data (lists, items, subscriptions, analytics, settings).
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc_args
	 */
	public function reset( array $args, array $assoc_args ): void {
		unset( $args );
		\WP_CLI::confirm( 'This permanently deletes ALL wishlist data. Continue?', $assoc_args );
		Resetter::reset_all();
		\WP_CLI::success( 'All Flexa Wishlist data removed.' );
	}

	/**
	 * Show top-line wishlist stats.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc_args
	 */
	public function stats( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		$totals = ( new DashboardRepository() )->totals();
		$rows   = [];
		foreach ( $totals as $key => $value ) {
			$rows[] = [
				'metric' => $key,
				'value'  => $value,
			];
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'metric', 'value' ] );
	}

	/**
	 * Run the guest-wishlist cleanup job now.
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args
	 * @param array<string,string> $assoc_args
	 */
	public function cleanup( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );
		Scheduler::instance()->run_guest_cleanup();
		\WP_CLI::success( 'Guest wishlist cleanup complete.' );
	}
}
