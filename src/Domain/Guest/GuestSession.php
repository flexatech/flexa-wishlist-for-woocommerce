<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Domain\Guest;

use Flexa\Wishlist\Domain\OwnerContext;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Guest identity via a signed, first-party cookie token (§18.1). The cookie is
 * `token.signature`; the server stores only the SHA-256 hash of the token in
 * the lists table, never the token itself. No cookie is set before the first
 * wishlist interaction (§24 B5) — reads use current_owner(); the first write
 * calls ensure_owner_for_write() which mints and sets the cookie.
 */
final class GuestSession {
	use SingletonTrait;

	public const COOKIE = 'flexa_wl_guest';

	/** Rolling lifetime seconds fallback; actual value derives from retention. */
	private const DEFAULT_TTL_DAYS = 30;

	private ?string $pending_token = null;

	public function register(): void {
		// Nothing to hook for reads; writes drive cookie creation. On a guest
		// interaction we refresh the cookie expiry (rolling window).
	}

	/**
	 * Resolve the current owner without creating anything. Logged-in users win;
	 * otherwise a valid guest cookie resolves to a guest hash; otherwise empty.
	 */
	public function current_owner(): OwnerContext {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return OwnerContext::for_user( $user_id );
		}

		$token = $this->read_valid_token();
		if ( null === $token ) {
			return new OwnerContext();
		}

		return OwnerContext::for_guest( $this->hash( $token ) );
	}

	/**
	 * Resolve the owner for a write, minting + setting a guest cookie if the
	 * visitor is a guest without one. Safe to call during a REST request (the
	 * cookie header is emitted before the JSON body).
	 */
	public function ensure_owner_for_write(): OwnerContext {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			return OwnerContext::for_user( $user_id );
		}

		$token = $this->read_valid_token();
		if ( null === $token ) {
			$token = $this->mint();
		}
		$this->set_cookie( $token );

		return OwnerContext::for_guest( $this->hash( $token ) );
	}

	/** The current guest token hash, if any (used by merge on login). */
	public function current_guest_hash(): ?string {
		$token = $this->read_valid_token();
		return null === $token ? null : $this->hash( $token );
	}

	/** Forget the guest cookie (after a successful merge). */
	public function clear_cookie(): void {
		$this->pending_token = null;
		if ( ! headers_sent() ) {
			setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, $this->cookie_path(), $this->cookie_domain(), is_ssl(), true );
		}
		unset( $_COOKIE[ self::COOKIE ] );
	}

	public function hash( string $token ): string {
		return hash( 'sha256', $token );
	}

	private function read_valid_token(): ?string {
		if ( null !== $this->pending_token ) {
			return $this->pending_token;
		}
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';
		if ( '' === $raw || ! str_contains( $raw, '.' ) ) {
			return null;
		}
		[ $token, $sig ] = explode( '.', $raw, 2 );
		if ( '' === $token || ! hash_equals( $this->sign( $token ), $sig ) ) {
			return null;
		}
		return $token;
	}

	private function mint(): string {
		$token               = wp_generate_password( 40, false );
		$this->pending_token = $token;
		return $token;
	}

	private function sign( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function set_cookie( string $token ): void {
		$this->pending_token = $token;
		if ( headers_sent() ) {
			return;
		}
		$value = $token . '.' . $this->sign( $token );
		setcookie(
			self::COOKIE,
			$value,
			[
				'expires'  => time() + $this->ttl_seconds(),
				'path'     => $this->cookie_path(),
				'domain'   => $this->cookie_domain(),
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
		$_COOKIE[ self::COOKIE ] = $value;
	}

	private function ttl_seconds(): int {
		$days = (int) apply_filters( 'flexa_wishlist/guest/retention_days', self::DEFAULT_TTL_DAYS );
		return max( 1, $days ) * DAY_IN_SECONDS;
	}

	private function cookie_path(): string {
		return defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	}

	private function cookie_domain(): string {
		return defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '';
	}
}
