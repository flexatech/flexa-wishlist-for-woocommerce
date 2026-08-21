<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Item;

defined( 'ABSPATH' ) || exit;

/**
 * A saved wishlist item. Mirrors one row of the items table. The attribute
 * summary and price are snapshots taken at save time; live product data is
 * re-fetched and re-validated at render (§16.2).
 */
final class Item {
	public function __construct(
		public readonly int $id,
		public readonly int $list_id,
		public readonly int $product_id,
		public readonly int $variation_id,
		public readonly string $attributes_summary,
		public readonly int $quantity,
		public readonly ?string $price_amount,
		public readonly string $price_currency,
		public readonly int $position,
		public readonly string $date_added,
	) {}

	/**
	 * @param array<string,mixed> $row
	 */
	public static function from_row( array $row ): self {
		return new self(
			id:                 (int) ( $row['id'] ?? 0 ),
			list_id:            (int) ( $row['list_id'] ?? 0 ),
			product_id:         (int) ( $row['product_id'] ?? 0 ),
			variation_id:       (int) ( $row['variation_id'] ?? 0 ),
			attributes_summary: (string) ( $row['attributes_summary'] ?? '' ),
			quantity:           max( 1, (int) ( $row['quantity'] ?? 1 ) ),
			price_amount:       isset( $row['price_amount'] )
				? (string) $row['price_amount']
				: null,
			price_currency:     (string) ( $row['price_currency'] ?? '' ),
			position:           (int) ( $row['position'] ?? 0 ),
			date_added:         (string) ( $row['date_added'] ?? '' ),
		);
	}

	/**
	 * Stable string key used by the storefront to reconcile saved state
	 * (§10.2). Format: "productId:variationId".
	 */
	public function key(): string {
		return $this->product_id . ':' . $this->variation_id;
	}

	/**
	 * The raw stored data (snapshots). The REST layer hydrates this with live
	 * WooCommerce product data (title, image, live price HTML, stock) before
	 * returning it to clients — the plugin never formats prices itself (§16.3).
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		$data = [
			'id'            => $this->id,
			'listId'        => $this->list_id,
			'productId'     => $this->product_id,
			'variationId'   => $this->variation_id,
			'key'           => $this->key(),
			'attributes'    => $this->attributes_summary,
			'quantity'      => $this->quantity,
			'priceSnapshot' => null !== $this->price_amount
				? [
					'amount'   => $this->price_amount,
					'currency' => $this->price_currency,
				]
				: null,
			'position'      => $this->position,
			'dateAdded'     => $this->date_added,
		];

		/** @var array<string,mixed> $data */
		$data = apply_filters( 'flexa_wishlist/item/data', $data, $this );

		return $data;
	}
}
