<?php

declare(strict_types=1);

namespace Flexa\Wishlist;

use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Boots every service in dependency order. Each subsystem self-gates (class
 * existence + its own settings toggle) so partial builds degrade gracefully.
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

		do_action( 'flexa_wishlist/booted', $this );
	}
}
