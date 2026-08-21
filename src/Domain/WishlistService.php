<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain;

use Flexa\Wishlist\Domain\Item\Item;
use Flexa\Wishlist\Domain\Item\ItemRepository;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Application service coordinating repositories + WooCommerce for the add /
 * remove / state use-cases shared by several controllers. Keeps snapshot capture
 * and the default-list guarantee in one place.
 */
final class WishlistService {
	private WishlistRepository $lists;
	private ItemRepository $items;

	public function __construct( ?WishlistRepository $lists = null, ?ItemRepository $items = null ) {
		$this->lists = $lists ?? new WishlistRepository();
		$this->items = $items ?? new ItemRepository();
	}

	public function lists(): WishlistRepository {
		return $this->lists;
	}

	public function items(): ItemRepository {
		return $this->items;
	}

	/**
	 * The cache-safe hydration payload (§17.1): the owner's total count, the set
	 * of saved keys ("productId:variationId") for reconciling button state, and
	 * lightweight list summaries.
	 *
	 * @return array{count:int, itemKeys:list<string>, lists:array<int,array<string,mixed>>}
	 */
	public function state( OwnerContext $owner ): array {
		if ( $owner->is_empty() ) {
			return [
				'count'    => 0,
				'itemKeys' => [],
				'lists'    => [],
			];
		}

		$owner_lists = $this->lists->all_for_owner( $owner );
		$list_ids    = array_map( static fn ( Wishlist $l ): int => $l->id, $owner_lists );

		$summaries = [];
		foreach ( $owner_lists as $list ) {
			$list->count = $this->items->count_for_list( $list->id );
			$summaries[] = $list->to_array();
		}

		return [
			'count'    => $this->items->count_for_lists( $list_ids ),
			'itemKeys' => $this->items->keys_for_lists( $list_ids ),
			'lists'    => $summaries,
		];
	}

	/**
	 * Add a product (optionally a variation) to a list. Ensures the owner has a
	 * default list; captures a price + attribute snapshot. Idempotent on dupes.
	 *
	 * @return array{item:Item,list:Wishlist,created:bool}|null Null on invalid product.
	 */
	public function add( OwnerContext $owner, int $product_id, int $variation_id = 0, int $quantity = 1, int $list_id = 0 ): ?array {
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}

		$list = $this->resolve_target_list( $owner, $list_id );
		if ( null === $list ) {
			return null;
		}

		$snapshot = $this->capture_snapshot( $product );

		$existing_id = $this->items->find_id( $list->id, $product_id, $variation_id );
		$item_id     = $this->items->add(
			$list->id,
			[
				'product_id'         => $product_id,
				'variation_id'       => $variation_id,
				'attributes_summary' => $snapshot['attributes'],
				'quantity'           => max( 1, $quantity ),
				'price_amount'       => $snapshot['amount'],
				'price_currency'     => $snapshot['currency'],
			]
		);

		$item    = $this->items->find( $item_id );
		$created = null === $existing_id;

		if ( $item instanceof Item && $created ) {
			do_action( 'flexa_wishlist/item/added', $item, $list, $owner );
		}

		return $item instanceof Item ? [
			'item'    => $item,
			'list'    => $list,
			'created' => $created,
		] : null;
	}

	/**
	 * The list a save should target: an explicit (owned) list id, else the
	 * owner's default, created on demand with the configured name.
	 */
	public function resolve_target_list( OwnerContext $owner, int $list_id = 0 ): ?Wishlist {
		if ( $list_id > 0 ) {
			$list = $this->lists->find( $list_id );
			if ( $list instanceof Wishlist && $this->lists->owns( $list, $owner ) ) {
				return $list;
			}
			return null;
		}

		$name = (string) Settings::get( 'general', 'default_list_name' );
		return $this->lists->default_for_owner( $owner, true, $name );
	}

	/**
	 * Capture a price + human-readable attribute snapshot at save time.
	 *
	 * @return array{amount:?string,currency:string,attributes:string}
	 */
	public function capture_snapshot( \WC_Product $product ): array {
		$price = $product->get_price();

		return [
			'amount'     => '' === (string) $price ? null : (string) $price,
			'currency'   => get_woocommerce_currency(),
			'attributes' => $this->attribute_summary( $product ),
		];
	}

	private function attribute_summary( \WC_Product $product ): string {
		if ( ! $product->is_type( 'variation' ) ) {
			return '';
		}

		$parts = [];
		foreach ( (array) $product->get_attributes() as $taxonomy => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$label     = wc_attribute_label( (string) $taxonomy, $product );
			$term_name = $value;
			if ( taxonomy_exists( (string) $taxonomy ) ) {
				$term = get_term_by( 'slug', (string) $value, (string) $taxonomy );
				if ( $term instanceof \WP_Term ) {
					$term_name = $term->name;
				}
			}
			$parts[] = $label . ': ' . $term_name;
		}

		return implode( ', ', $parts );
	}
}
