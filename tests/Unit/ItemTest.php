<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Tests\Unit;

use Flexa\Wishlist\Domain\Item\Item;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase {

	public function test_from_row_coerces_types(): void {
		$item = Item::from_row(
			[
				'id'           => '5',
				'list_id'      => '3',
				'product_id'   => '99',
				'variation_id' => '0',
				'quantity'     => '2',
				'position'     => '7',
			]
		);

		$this->assertSame( 5, $item->id );
		$this->assertSame( 3, $item->list_id );
		$this->assertSame( 99, $item->product_id );
		$this->assertSame( 0, $item->variation_id );
		$this->assertSame( 2, $item->quantity );
		$this->assertSame( 7, $item->position );
	}

	public function test_quantity_is_at_least_one(): void {
		$item = Item::from_row( [ 'quantity' => '0' ] );

		$this->assertSame( 1, $item->quantity );
	}

	public function test_price_is_null_when_absent_and_string_when_present(): void {
		$without = Item::from_row( [ 'product_id' => 1 ] );
		$with    = Item::from_row( [ 'product_id' => 1, 'price_amount' => '19.99' ] );

		$this->assertNull( $without->price_amount );
		$this->assertSame( '19.99', $with->price_amount );
	}

	public function test_key_is_product_colon_variation(): void {
		$item = Item::from_row( [ 'product_id' => 12, 'variation_id' => 34 ] );

		$this->assertSame( '12:34', $item->key() );
	}

	public function test_to_array_shape_without_price_snapshot(): void {
		$item = Item::from_row( [ 'id' => 1, 'product_id' => 2, 'variation_id' => 3 ] );
		$data = $item->to_array();

		$this->assertSame( 1, $data['id'] );
		$this->assertSame( '2:3', $data['key'] );
		$this->assertNull( $data['priceSnapshot'] );
		$this->assertArrayHasKey( 'dateAdded', $data );
	}

	public function test_to_array_includes_price_snapshot_when_present(): void {
		$item = Item::from_row(
			[
				'product_id'     => 2,
				'price_amount'   => '9.50',
				'price_currency' => 'USD',
			]
		);
		$data = $item->to_array();

		$this->assertIsArray( $data['priceSnapshot'] );
		$this->assertSame( '9.50', $data['priceSnapshot']['amount'] );
		$this->assertSame( 'USD', $data['priceSnapshot']['currency'] );
	}
}
