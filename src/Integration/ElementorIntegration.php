<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Integration;

use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Elementor integration entry point (§10.10). Boots only once Elementor itself
 * has loaded, then hands off to a bootstrap file that lives under
 * `Elementor/` with every `\Elementor\*` reference. That directory is kept out
 * of static analysis (no Elementor stubs ship) — this class stays clean because
 * it touches no Elementor symbol, only a require of the bootstrap on the
 * `elementor/loaded` hook (which fires exclusively when Elementor is active).
 */
final class ElementorIntegration {
	use SingletonTrait;

	public function register(): void {
		add_action( 'elementor/loaded', [ $this, 'boot' ] );
	}

	public function boot(): void {
		require_once __DIR__ . '/Elementor/bootstrap.php';
	}
}
