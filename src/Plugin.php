<?php

declare(strict_types=1);

namespace Flexa\Wishlist;

use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Boots every service in dependency order. Each subsystem self-gates (class
 * existence + its own settings toggle) so partial builds and the Free/Pro split
 * degrade gracefully. Pro boots last, by file presence, gated on the licence
 * filter — never a Free-side flag guarding shipped-but-disabled code.
 */
final class Plugin {
	use SingletonTrait;

	private bool $booted = false;

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		add_action( 'admin_init', [ Install\Migrator::class, 'maybe_upgrade' ] );

		// REST API — the single source of storefront + admin data.
		Rest\RegisterFacade::instance()->register();

		// Admin app (menu + Vite enqueue). Only mounts on the plugin's screen.
		if ( is_admin() ) {
			Admin\AdminMenu::instance()->register();
			Admin\Enqueue::instance()->register();
		}

		// Storefront surfaces (button, counter, page, shortcodes, hydration).
		Frontend\Assets::instance()->register();
		Frontend\ButtonRenderer::instance()->register();
		Frontend\Shortcodes::instance()->register();

		// Guest lifecycle + account merge + scheduled hygiene.
		Domain\Guest\GuestSession::instance()->register();
		Domain\Merge\MergeService::instance()->register();
		Support\Scheduler::instance()->register();

		// GDPR exporter/eraser + rewrite for pretty share URLs.
		Integration\Privacy::instance()->register();
		Integration\ShareRoute::instance()->register();

		// Page-builder surfaces — thin wrappers over the shortcode renderers.
		Integration\Blocks::instance()->register();
		Integration\ElementorIntegration::instance()->register();

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			Cli\WishlistCommand::register();
		}

		$this->boot_pro();

		do_action( 'flexa_wishlist/booted', $this );
	}

	/**
	 * Boot the Pro layer if its files are present and it reports a valid
	 * licence. Presence is file-level (src-pro/); the licence gate is the
	 * `flexa_wishlist/pro/is_licensed` filter (default false in Free).
	 */
	private function boot_pro(): void {
		if ( defined( 'FLEXA_WISHLIST_DISABLE_PRO' ) && \FLEXA_WISHLIST_DISABLE_PRO ) {
			return;
		}
		if ( ! class_exists( \Flexa\WishlistPro\Plugin::class ) ) {
			return;
		}
		\Flexa\WishlistPro\Plugin::instance()->boot();
	}
}
