<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Support\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * The wishlist page (§10.4). Rendered as a cache-safe client-hydrated container:
 * the server emits a user-agnostic shell (loading skeleton + designed empty
 * state + shop CTA) and the storefront script fetches GET /lists/{default} and
 * renders the card list. No per-user markup ships in the cacheable HTML (§17.1).
 */
final class PageRenderer {
	/**
	 * @param array<string,mixed> $atts
	 */
	public static function html( array $atts = [] ): string {
		$layout    = isset( $atts['layout'] ) && in_array( $atts['layout'], Settings::PAGE_LAYOUTS, true )
			? (string) $atts['layout']
			: (string) Settings::get( 'page', 'layout' );
		$shop_url  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$show_cart = (bool) Settings::get( 'page', 'add_to_cart' );

		ob_start();
		?>
		<div class="fw-page fw-page--<?php echo esc_attr( $layout ); ?>" data-fw-page data-fw-layout="<?php echo esc_attr( $layout ); ?>">
			<div class="fw-page__loading" data-fw-page-loading>
				<span class="fw-spinner" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Loading your wishlist…', 'flexa-woocommerce-wishlist' ); ?></span>
			</div>

			<div class="fw-page__empty" data-fw-page-empty hidden>
				<div class="fw-empty">
					<div class="fw-empty__icon" aria-hidden="true">
						<?php echo ButtonRenderer::icon_svg( (string) Settings::get( 'appearance', 'icon' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<h2 class="fw-empty__title"><?php esc_html_e( 'Your wishlist is empty', 'flexa-woocommerce-wishlist' ); ?></h2>
					<p class="fw-empty__text"><?php esc_html_e( 'Save items you love by tapping the heart. They’ll show up here.', 'flexa-woocommerce-wishlist' ); ?></p>
					<a class="fw-btn-cta" href="<?php echo esc_url( (string) $shop_url ); ?>"><?php esc_html_e( 'Start shopping', 'flexa-woocommerce-wishlist' ); ?></a>
				</div>
			</div>

			<?php if ( $show_cart ) : ?>
				<div class="fw-page__toolbar" data-fw-page-toolbar hidden>
					<button type="button" class="fw-btn-cta fw-add-all" data-fw-add-all hidden>
						<?php esc_html_e( 'Add all to cart', 'flexa-woocommerce-wishlist' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<ul class="fw-page__list" data-fw-page-list hidden></ul>

			<div class="fw-page__footer" data-fw-page-footer hidden>
				<button type="button" class="fw-btn-cta fw-btn-cta--ghost" data-fw-load-more hidden><?php esc_html_e( 'Load more', 'flexa-woocommerce-wishlist' ); ?></button>
			</div>

			<noscript>
				<p><?php esc_html_e( 'Your wishlist requires JavaScript to display.', 'flexa-woocommerce-wishlist' ); ?></p>
			</noscript>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
