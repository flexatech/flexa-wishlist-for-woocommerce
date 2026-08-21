<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Wishlist;

defined( 'ABSPATH' ) || exit;

/**
 * A wishlist (the "list" entity). Mirrors one row of the lists table, with an
 * optional transient `count` the repository populates when asked.
 */
final class Wishlist {
	public function __construct(
		public readonly int $id,
		public readonly int $owner_user_id,
		public readonly ?string $guest_token_hash,
		public readonly string $name,
		public readonly bool $is_default,
		public readonly string $visibility,
		public readonly ?string $share_slug,
		public readonly string $created_at,
		public readonly string $updated_at,
		public int $count = 0,
	) {}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function from_row( array $row ): self {
		return new self(
			id:               (int) ( $row['id'] ?? 0 ),
			owner_user_id:    (int) ( $row['owner_user_id'] ?? 0 ),
			guest_token_hash: isset( $row['guest_token_hash'] ) && '' !== (string) $row['guest_token_hash']
				? (string) $row['guest_token_hash']
				: null,
			name:             (string) ( $row['name'] ?? '' ),
			is_default:       (bool) ( $row['is_default'] ?? false ),
			visibility:       (string) ( $row['visibility'] ?? 'private' ),
			share_slug:       isset( $row['share_slug'] ) && '' !== (string) $row['share_slug']
				? (string) $row['share_slug']
				: null,
			created_at:       (string) ( $row['created_at'] ?? '' ),
			updated_at:       (string) ( $row['updated_at'] ?? '' ),
			count:            (int) ( $row['count'] ?? 0 ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$data = [
			'id'         => $this->id,
			'name'       => $this->name,
			'isDefault'  => $this->is_default,
			'visibility' => $this->visibility,
			'shareSlug'  => $this->share_slug,
			'count'      => $this->count,
			'createdAt'  => $this->created_at,
			'updatedAt'  => $this->updated_at,
		];

		/** @var array<string,mixed> $data */
		$data = apply_filters( 'flexa_wishlist/list/data', $data, $this );

		return $data;
	}
}
