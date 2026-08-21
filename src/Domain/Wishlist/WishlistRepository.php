<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Wishlist;

use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Install\Migrator;

defined( 'ABSPATH' ) || exit;

/*
 * All lists-table SQL lives here. Every dynamic value passes through
 * $wpdb->prepare(); table names come from $wpdb->prefix + a Migrator constant.
 * The sniffs below can't see that the IN() lists are placeholder-only, so they
 * are disabled for this data-access class (per lineage convention).
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders

/**
 * Repository for wishlists (the list entity). Returns Wishlist value objects.
 */
final class WishlistRepository {
	public function table(): string {
		global $wpdb;
		return $wpdb->prefix . Migrator::LIST_TABLE;
	}

	public function find( int $id ): ?Wishlist {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ),
			ARRAY_A
		);
		return is_array( $row ) ? Wishlist::from_row( $row ) : null;
	}

	public function find_by_slug( string $slug ): ?Wishlist {
		global $wpdb;
		if ( '' === $slug ) {
			return null;
		}
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE share_slug = %s', $this->table(), $slug ),
			ARRAY_A
		);
		return is_array( $row ) ? Wishlist::from_row( $row ) : null;
	}

	/**
	 * Every list owned by the given owner, default first.
	 *
	 * @return Wishlist[]
	 */
	public function all_for_owner( OwnerContext $owner ): array {
		global $wpdb;
		if ( $owner->is_empty() ) {
			return [];
		}

		if ( $owner->is_user() ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE owner_user_id = %d ORDER BY is_default DESC, id ASC',
					$this->table(),
					$owner->user_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE guest_token_hash = %s ORDER BY is_default DESC, id ASC',
					$this->table(),
					(string) $owner->guest_hash
				),
				ARRAY_A
			);
		}

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[] = Wishlist::from_row( $row );
		}
		return $out;
	}

	/**
	 * @return int[] the owner's list ids
	 */
	public function ids_for_owner( OwnerContext $owner ): array {
		return array_map( static fn ( Wishlist $l ): int => $l->id, $this->all_for_owner( $owner ) );
	}

	/**
	 * The owner's default list, optionally created on demand.
	 */
	public function default_for_owner( OwnerContext $owner, bool $create = false, string $name = '' ): ?Wishlist {
		foreach ( $this->all_for_owner( $owner ) as $list ) {
			if ( $list->is_default ) {
				return $list;
			}
		}

		if ( ! $create || $owner->is_empty() ) {
			return null;
		}

		$id = $this->create(
			[
				'owner_user_id'    => $owner->user_id,
				'guest_token_hash' => $owner->guest_hash,
				'name'             => '' !== $name ? $name : __( 'Favorites', 'flexa-woocommerce-wishlist' ),
				'is_default'       => true,
			]
		);

		return $id > 0 ? $this->find( $id ) : null;
	}

	/**
	 * @param array{owner_user_id?:int,guest_token_hash?:string|null,name?:string,is_default?:bool,visibility?:string,share_slug?:string|null} $data
	 */
	public function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$wpdb->insert(
			$this->table(),
			[
				'owner_user_id'    => (int) ( $data['owner_user_id'] ?? 0 ),
				'guest_token_hash' => $data['guest_token_hash'] ?? null,
				'name'             => (string) ( $data['name'] ?? __( 'Favorites', 'flexa-woocommerce-wishlist' ) ),
				'is_default'       => ! empty( $data['is_default'] ) ? 1 : 0,
				'visibility'       => (string) ( $data['visibility'] ?? 'private' ),
				'share_slug'       => $data['share_slug'] ?? null,
				'created_at'       => $now,
				'updated_at'       => $now,
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		$id = (int) $wpdb->insert_id;
		if ( $id > 0 ) {
			do_action( 'flexa_wishlist/list/created', $id, $data );
		}
		return $id;
	}

	/**
	 * @param array<string,mixed> $changes
	 */
	public function update( int $id, array $changes ): bool {
		global $wpdb;

		$allowed = [];
		$formats = [];
		if ( array_key_exists( 'name', $changes ) ) {
			$allowed['name'] = (string) $changes['name'];
			$formats[]       = '%s';
		}
		if ( array_key_exists( 'visibility', $changes ) ) {
			$allowed['visibility'] = (string) $changes['visibility'];
			$formats[]             = '%s';
		}
		if ( array_key_exists( 'share_slug', $changes ) ) {
			$allowed['share_slug'] = null === $changes['share_slug'] ? null : (string) $changes['share_slug'];
			$formats[]             = '%s';
		}
		if ( [] === $allowed ) {
			return false;
		}

		$allowed['updated_at'] = current_time( 'mysql', true );
		$formats[]             = '%s';

		$result = $wpdb->update( $this->table(), $allowed, [ 'id' => $id ], $formats, [ '%d' ] );
		return false !== $result;
	}

	public function delete( int $id ): int {
		global $wpdb;
		$rows = (int) $wpdb->delete( $this->table(), [ 'id' => $id ], [ '%d' ] );
		if ( $rows > 0 ) {
			do_action( 'flexa_wishlist/list/deleted', $id );
		}
		return $rows;
	}

	/**
	 * Reassign every list owned by a guest hash to a user id (used by merge for
	 * the fast path when the user has no lists yet).
	 */
	public function reassign_guest_to_user( string $guest_hash, int $user_id ): int {
		global $wpdb;
		return (int) $wpdb->update(
			$this->table(),
			[
				'owner_user_id'    => $user_id,
				'guest_token_hash' => null,
				'updated_at'       => current_time( 'mysql', true ),
			],
			[ 'guest_token_hash' => $guest_hash ],
			[ '%d', '%s', '%s' ],
			[ '%s' ]
		);
	}

	/**
	 * True when the owner context owns the list. Ownership is validated
	 * server-side on every mutating route (§21) — ids are never trusted.
	 */
	public function owns( Wishlist $list, OwnerContext $owner ): bool {
		if ( $owner->is_user() ) {
			return $list->owner_user_id === $owner->user_id;
		}
		if ( $owner->is_guest() ) {
			return $list->guest_token_hash === $owner->guest_hash;
		}
		return false;
	}
}
