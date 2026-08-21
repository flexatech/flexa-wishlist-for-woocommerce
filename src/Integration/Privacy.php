<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration;

use Flexa\Wishlist\Domain\Item\ItemRepository;
use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Domain\Product\ProductHydrator;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * GDPR personal-data exporter + eraser (§18.2). Registers a "Wishlists" group
 * covering a user's lists, saved items, and (Pro) stock subscriptions keyed by
 * their account email.
 */
final class Privacy {
	use SingletonTrait;

	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * @param array<string,array<string,mixed>> $exporters
	 * @return array<string,array<string,mixed>>
	 */
	public function register_exporter( array $exporters ): array {
		$exporters['flexa-wishlist'] = [
			'exporter_friendly_name' => __( 'Wishlists', 'flexa-woocommerce-wishlist' ),
			'callback'               => [ $this, 'export' ],
		];
		return $exporters;
	}

	/**
	 * @param array<string,array<string,mixed>> $erasers
	 * @return array<string,array<string,mixed>>
	 */
	public function register_eraser( array $erasers ): array {
		$erasers['flexa-wishlist'] = [
			'eraser_friendly_name' => __( 'Wishlists', 'flexa-woocommerce-wishlist' ),
			'callback'             => [ $this, 'erase' ],
		];
		return $erasers;
	}

	/**
	 * @return array{data:array<int,array<string,mixed>>,done:bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $page );
		$user = get_user_by( 'email', $email );
		$data = [];

		if ( $user instanceof \WP_User ) {
			$lists   = new WishlistRepository();
			$items   = new ItemRepository();
			$hydrate = new ProductHydrator();
			foreach ( $lists->all_for_owner( OwnerContext::for_user( (int) $user->ID ) ) as $list ) {
				foreach ( $items->for_list( $list->id, 1, 1000 ) as $item ) {
					$hydrated = $hydrate->hydrate( $item );
					$name     = isset( $hydrated['product']['name'] ) ? (string) $hydrated['product']['name'] : (string) ( $hydrated['name'] ?? '' );
					$data[]   = [
						'group_id'    => 'flexa-wishlist',
						'group_label' => __( 'Wishlists', 'flexa-woocommerce-wishlist' ),
						'item_id'     => 'flexa-wl-item-' . $item->id,
						'data'        => [
							[
								'name'  => __( 'List', 'flexa-woocommerce-wishlist' ),
								'value' => $list->name,
							],
							[
								'name'  => __( 'Product', 'flexa-woocommerce-wishlist' ),
								'value' => $name,
							],
							[
								'name'  => __( 'Saved on', 'flexa-woocommerce-wishlist' ),
								'value' => $item->date_added,
							],
						],
					];
				}
			}
		}

		return [
			'data' => $data,
			'done' => true,
		];
	}

	/**
	 * @return array{items_removed:bool,items_retained:bool,messages:array<int,string>,done:bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		$user    = get_user_by( 'email', $email );
		$removed = false;

		if ( $user instanceof \WP_User ) {
			$lists = new WishlistRepository();
			$items = new ItemRepository();
			foreach ( $lists->all_for_owner( OwnerContext::for_user( (int) $user->ID ) ) as $list ) {
				$items->delete_for_list( $list->id );
				$lists->delete( $list->id );
				$removed = true;
			}
		}

		return [
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}
}
