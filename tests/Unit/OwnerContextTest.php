<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Tests\Unit;

use Flexa\Wishlist\Domain\OwnerContext;
use PHPUnit\Framework\TestCase;

final class OwnerContextTest extends TestCase {

	public function test_for_user_is_a_user(): void {
		$owner = OwnerContext::for_user( 42 );

		$this->assertTrue( $owner->is_user() );
		$this->assertFalse( $owner->is_guest() );
		$this->assertFalse( $owner->is_empty() );
		$this->assertSame( 42, $owner->user_id );
		$this->assertNull( $owner->guest_hash );
	}

	public function test_for_guest_is_a_guest(): void {
		$owner = OwnerContext::for_guest( 'abc123' );

		$this->assertTrue( $owner->is_guest() );
		$this->assertFalse( $owner->is_user() );
		$this->assertFalse( $owner->is_empty() );
		$this->assertSame( 0, $owner->user_id );
		$this->assertSame( 'abc123', $owner->guest_hash );
	}

	public function test_default_context_is_empty(): void {
		$owner = new OwnerContext();

		$this->assertTrue( $owner->is_empty() );
		$this->assertFalse( $owner->is_user() );
		$this->assertFalse( $owner->is_guest() );
	}

	public function test_user_id_zero_is_not_a_user(): void {
		$owner = OwnerContext::for_user( 0 );

		$this->assertFalse( $owner->is_user() );
		$this->assertTrue( $owner->is_empty() );
	}

	public function test_exactly_one_identity_is_populated(): void {
		$user  = OwnerContext::for_user( 7 );
		$guest = OwnerContext::for_guest( 'hash' );

		$this->assertNotSame( $user->is_user(), $user->is_guest() );
		$this->assertNotSame( $guest->is_user(), $guest->is_guest() );
	}
}
