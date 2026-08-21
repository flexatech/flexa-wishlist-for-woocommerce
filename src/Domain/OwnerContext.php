<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * Identifies the current wishlist owner: either a logged-in user
 * (user_id > 0) or a guest keyed by the SHA-256 hash of their signed cookie
 * token (guest_hash set). Exactly one of the two is populated for a resolved
 * owner; an empty context means the visitor has no wishlist identity yet
 * (no cookie, not logged in — §18.1).
 */
final class OwnerContext {
	public function __construct(
		public readonly int $user_id = 0,
		public readonly ?string $guest_hash = null,
	) {}

	public static function for_user( int $user_id ): self {
		return new self( $user_id, null );
	}

	public static function for_guest( string $guest_hash ): self {
		return new self( 0, $guest_hash );
	}

	public function is_guest(): bool {
		return 0 === $this->user_id && null !== $this->guest_hash;
	}

	public function is_user(): bool {
		return $this->user_id > 0;
	}

	public function is_empty(): bool {
		return 0 === $this->user_id && null === $this->guest_hash;
	}
}
