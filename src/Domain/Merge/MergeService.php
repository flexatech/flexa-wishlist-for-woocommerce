<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Merge;

use Flexa\Wishlist\Domain\Guest\GuestSession;
use Flexa\Wishlist\Domain\Item\ItemRepository;
use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Silent, additive, idempotent guest→account merge (§9.9, decision §23.3.3).
 * On login: take the guest's default list, union its items into the user's
 * default list deduped by (product, variation), then delete the guest list and
 * clear the cookie so a repeat login can never duplicate (§24 B2/B3).
 */
final class MergeService {
	use SingletonTrait;

	public function register(): void {
		// Fire after the auth cookie is set and current user resolves.
		add_action( 'wp_login', [ $this, 'on_login' ], 20, 2 );
	}

	/**
	 * @param string        $user_login
	 * @param \WP_User|null $user
	 */
	public function on_login( $user_login, $user = null ): void {
		unset( $user_login );
		$user_id = $user instanceof \WP_User ? (int) $user->ID : 0;
		if ( $user_id <= 0 ) {
			return;
		}

		$guest_hash = GuestSession::instance()->current_guest_hash();
		if ( null === $guest_hash ) {
			return;
		}

		$this->merge_guest_into_user( $guest_hash, $user_id );
		GuestSession::instance()->clear_cookie();
	}

	/**
	 * @return int Number of items brought over (new to the account).
	 */
	public function merge_guest_into_user( string $guest_hash, int $user_id ): int {
		$lists = new WishlistRepository();
		$items = new ItemRepository();

		$guest_owner = OwnerContext::for_guest( $guest_hash );
		$user_owner  = OwnerContext::for_user( $user_id );

		$guest_default = $lists->default_for_owner( $guest_owner );
		if ( null === $guest_default ) {
			return 0;
		}

		$user_default = $lists->default_for_owner( $user_owner );

		// Fast path: user has no list yet — reassign the guest list wholesale.
		if ( null === $user_default ) {
			$moved = $lists->reassign_guest_to_user( $guest_hash, $user_id );
			$count = $items->count_for_list( $guest_default->id );
			do_action( 'flexa_wishlist/guest/merged', $guest_hash, $user_id, $count );
			// Reassigning may bring over multiple guest lists; ensure exactly one
			// stays default (the former guest default already is).
			unset( $moved );
			return $count;
		}

		$brought = 0;
		$rows    = $items->raw_rows_for_list( $guest_default->id );
		foreach ( $rows as $row ) {
			$before = $items->find_id( $user_default->id, (int) $row['product_id'], (int) $row['variation_id'] );
			$items->restore( $user_default->id, $row );
			if ( null === $before ) {
				++$brought;
			}
		}

		// Remove the now-merged guest data entirely.
		$items->delete_for_list( $guest_default->id );
		$lists->delete( $guest_default->id );

		do_action( 'flexa_wishlist/guest/merged', $guest_hash, $user_id, $brought );

		return $brought;
	}
}
