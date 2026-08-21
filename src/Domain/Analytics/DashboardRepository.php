<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Analytics;

use Flexa\Wishlist\Install\Migrator;

defined( 'ABSPATH' ) || exit;

/*
 * Read-only aggregate queries for the admin dashboard (§11.1). All values are
 * bound through $wpdb->prepare(); table names come from Migrator constants.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

final class DashboardRepository {
	private function lists_table(): string {
		global $wpdb;
		return $wpdb->prefix . Migrator::LIST_TABLE;
	}

	private function items_table(): string {
		global $wpdb;
		return $wpdb->prefix . Migrator::ITEM_TABLE;
	}

	/**
	 * @return array{totalItems:int,totalLists:int,activeWishlists:int,guestWishlists:int}
	 */
	public function totals(): array {
		global $wpdb;
		$lists = $this->lists_table();
		$items = $this->items_table();

		$total_items = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $items ) );
		$total_lists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $lists ) );
		$guest_lists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE guest_token_hash IS NOT NULL', $lists ) );
		$active      = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(DISTINCT list_id) FROM %i', $items )
		);

		return [
			'totalItems'      => $total_items,
			'totalLists'      => $total_lists,
			'activeWishlists' => $active,
			'guestWishlists'  => $guest_lists,
		];
	}

	/**
	 * Most-wishlisted products (distinct-list demand). Names are resolved at
	 * read time via WooCommerce so deleted products drop out gracefully.
	 *
	 * @return array<int,array{productId:int,count:int,name:string}>
	 */
	public function top_products( int $limit = 5 ): array {
		global $wpdb;
		$items = $this->items_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT product_id, COUNT(DISTINCT list_id) AS c
				 FROM %i
				 GROUP BY product_id
				 ORDER BY c DESC
				 LIMIT %d',
				$items,
				max( 1, $limit )
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$product_id = (int) $row['product_id'];
			$product    = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$out[] = [
				'productId' => $product_id,
				'count'     => (int) $row['c'],
				'name'      => $product->get_name(),
			];
		}
		return $out;
	}
}
