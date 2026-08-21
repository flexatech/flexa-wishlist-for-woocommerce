<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Item;

use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Install\Migrator;

defined( 'ABSPATH' ) || exit;

/*
 * All items-table SQL lives here. Every dynamic value passes through
 * $wpdb->prepare(); table names come from $wpdb->prefix + a Migrator constant;
 * the dynamic IN() lists are placeholder-only (built from array_fill('%d')) with
 * the actual ints bound through prepare(). The sniffs can't see inside those
 * patterns, so they are disabled for this data-access class.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

/**
 * Repository for wishlist items. Returns Item value objects.
 */
final class ItemRepository {
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . Migrator::ITEM_TABLE;
	}

	public function find( int $id ): ?Item {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ),
			ARRAY_A
		);
		return is_array( $row ) ? Item::from_row( $row ) : null;
	}

	/**
	 * The id of an existing item matching (list, product, variation), or null.
	 */
	public function find_id( int $list_id, int $product_id, int $variation_id ): ?int {
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE list_id = %d AND product_id = %d AND variation_id = %d',
				$this->table(),
				$list_id,
				$product_id,
				$variation_id
			)
		);
		return null === $id ? null : (int) $id;
	}

	/**
	 * One page of a list's items, newest position first.
	 *
	 * @return Item[]
	 */
	public function for_list( int $list_id, int $page = 1, int $per_page = 24 ): array {
		global $wpdb;
		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE list_id = %d ORDER BY position DESC, id DESC LIMIT %d OFFSET %d',
				$this->table(),
				$list_id,
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = Item::from_row( $row );
		}
		return $out;
	}

	public function count_for_list( int $list_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE list_id = %d', $this->table(), $list_id )
		);
	}

	/**
	 * Distinct product/variation keys saved anywhere in the owner's lists, for
	 * the cache-safe /state hydration call (§17.1). Returns "productId:variationId".
	 *
	 * @param int[] $list_ids
	 * @return list<string>
	 */
	public function keys_for_lists( array $list_ids ): array {
		global $wpdb;
		$list_ids = array_values( array_filter( array_map( 'intval', $list_ids ), static fn ( int $i ): bool => $i > 0 ) );
		if ( [] === $list_ids ) {
			return [];
		}
		$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT product_id, variation_id FROM %i WHERE list_id IN ({$placeholders})",
				array_merge( [ $this->table() ], $list_ids )
			),
			ARRAY_A
		);

		$keys = [];
		foreach ( (array) $rows as $row ) {
			$keys[] = (int) $row['product_id'] . ':' . (int) $row['variation_id'];
		}
		return $keys;
	}

	/**
	 * @param int[] $list_ids
	 */
	public function count_for_lists( array $list_ids ): int {
		global $wpdb;
		$list_ids = array_values( array_filter( array_map( 'intval', $list_ids ), static fn ( int $i ): bool => $i > 0 ) );
		if ( [] === $list_ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $list_ids ), '%d' ) );
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE list_id IN ({$placeholders})",
				array_merge( [ $this->table() ], $list_ids )
			)
		);
	}

	/**
	 * Add an item. Idempotent on the unique (list, product, variation) key: a
	 * re-add returns the existing item's id rather than erroring (§9.9).
	 *
	 * @param array{product_id:int,variation_id?:int,attributes_summary?:string,quantity?:int,price_amount?:string|null,price_currency?:string} $data
	 */
	public function add( int $list_id, array $data ): int {
		global $wpdb;

		$product_id   = (int) $data['product_id'];
		$variation_id = (int) ( $data['variation_id'] ?? 0 );

		$existing = $this->find_id( $list_id, $product_id, $variation_id );
		if ( null !== $existing ) {
			return $existing;
		}

		$wpdb->insert(
			$this->table(),
			[
				'list_id'            => $list_id,
				'product_id'         => $product_id,
				'variation_id'       => $variation_id,
				'attributes_summary' => (string) ( $data['attributes_summary'] ?? '' ),
				'quantity'           => max( 1, (int) ( $data['quantity'] ?? 1 ) ),
				'price_amount'       => $data['price_amount'] ?? null,
				'price_currency'     => (string) ( $data['price_currency'] ?? '' ),
				'position'           => $this->next_position( $list_id ),
				'date_added'         => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Insert an item preserving an explicit date_added/position (used by merge
	 * and undo-restore so an item keeps its original ordering — §24 A2/B2).
	 *
	 * @param array<string,mixed> $row
	 */
	public function restore( int $list_id, array $row ): int {
		global $wpdb;

		$product_id   = (int) ( $row['product_id'] ?? 0 );
		$variation_id = (int) ( $row['variation_id'] ?? 0 );

		$existing = $this->find_id( $list_id, $product_id, $variation_id );
		if ( null !== $existing ) {
			return $existing;
		}

		$wpdb->insert(
			$this->table(),
			[
				'list_id'            => $list_id,
				'product_id'         => $product_id,
				'variation_id'       => $variation_id,
				'attributes_summary' => (string) ( $row['attributes_summary'] ?? '' ),
				'quantity'           => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				'price_amount'       => $row['price_amount'] ?? null,
				'price_currency'     => (string) ( $row['price_currency'] ?? '' ),
				'position'           => (int) ( $row['position'] ?? $this->next_position( $list_id ) ),
				'date_added'         => (string) ( $row['date_added'] ?? current_time( 'mysql', true ) ),
			],
			[ '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	public function update( int $id, array $changes ): bool {
		global $wpdb;

		$allowed = [];
		$formats = [];
		if ( array_key_exists( 'quantity', $changes ) ) {
			$allowed['quantity'] = max( 1, (int) $changes['quantity'] );
			$formats[]           = '%d';
		}
		if ( array_key_exists( 'position', $changes ) ) {
			$allowed['position'] = (int) $changes['position'];
			$formats[]           = '%d';
		}
		if ( array_key_exists( 'list_id', $changes ) ) {
			$allowed['list_id'] = (int) $changes['list_id'];
			$formats[]          = '%d';
		}
		if ( [] === $allowed ) {
			return false;
		}

		return false !== $wpdb->update( $this->table(), $allowed, [ 'id' => $id ], $formats, [ '%d' ] );
	}

	public function delete( int $id ): int {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
	}

	public function delete_for_list( int $list_id ): int {
		global $wpdb;
		return (int) $wpdb->delete( $this->table(), [ 'list_id' => $list_id ], [ '%d' ] );
	}

	/**
	 * All raw rows of a list (used by merge to move items between owners).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function raw_rows_for_list( int $list_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE list_id = %d ORDER BY position ASC, id ASC', $this->table(), $list_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	public function next_position( int $list_id ): int {
		global $wpdb;
		$max = $wpdb->get_var(
			$wpdb->prepare( 'SELECT MAX(position) FROM %i WHERE list_id = %d', $this->table(), $list_id )
		);
		return null === $max ? 0 : ( (int) $max ) + 1;
	}

	/**
	 * True when the item belongs to one of the owner's lists.
	 *
	 * @param int[] $owner_list_ids
	 */
	public function owned_by( Item $item, array $owner_list_ids ): bool {
		return in_array( $item->list_id, array_map( 'intval', $owner_list_ids ), true );
	}
}
