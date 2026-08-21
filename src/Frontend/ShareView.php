<?php

declare(strict_types=1);

namespace Flexa\Wishlist\Frontend;

use Flexa\Wishlist\Domain\Product\ProductHydrator;
use Flexa\Wishlist\Domain\Wishlist\Wishlist;
use Flexa\Wishlist\Domain\Wishlist\WishlistRepository;
use Flexa\Wishlist\Domain\WishlistService;
use Flexa\Wishlist\Support\Settings;
use Flexa\Wishlist\Support\SingletonTrait;

defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered public shared view (§10.4/§20.3). SSR (not client-hydrated) so
 * the OG/Twitter meta is correct for link unfurls and the page is indexable when
 * the merchant allows it. Private/missing lists get a neutral "not available"
 * screen — never a disclosure of existence (§18.5).
 */
final class ShareView {
	use SingletonTrait;

	public function render( string $slug ): void {
		$list = ( new WishlistRepository() )->find_by_slug( $slug );

		if ( ! $list instanceof Wishlist || 'private' === $list->visibility ) {
			$this->render_unavailable();
			return;
		}

		$this->render_list( $list );
	}

	private function render_list( Wishlist $list ): void {
		$service = new WishlistService();
		$items   = ( new ProductHydrator() )->hydrate_many( $service->items()->for_list( $list->id, 1, 200 ) );

		$allow_index = 'public' === $list->visibility && (bool) Settings::get( 'sharing', 'allow_indexing' );
		$this->head( $list, $items, $allow_index );

		Assets::instance()->enqueue();
		status_header( 200 );
		get_header();
		?>
		<div class="fw-shared" data-fw-shared>
			<header class="fw-shared__header">
				<h1 class="fw-shared__title"><?php echo esc_html( $list->name ); ?></h1>
				<p class="fw-shared__owner"><?php echo esc_html( $this->owner_line( $list ) ); ?></p>
			</header>

			<?php if ( empty( $items ) ) : ?>
				<p class="fw-shared__empty"><?php esc_html_e( 'This wishlist has no items yet.', 'flexa-woocommerce-wishlist' ); ?></p>
			<?php else : ?>
				<div class="fw-shared__actions">
					<button type="button" class="fw-btn-cta" data-fw-save-all data-fw-slug="<?php echo esc_attr( (string) $list->share_slug ); ?>">
						<?php esc_html_e( 'Save all to my wishlist', 'flexa-woocommerce-wishlist' ); ?>
					</button>
				</div>
				<ul class="fw-page__list fw-page--grid" data-fw-shared-list>
					<?php foreach ( $items as $item ) : ?>
						<?php echo $this->card( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		get_footer();
	}

	private function render_unavailable(): void {
		status_header( 404 );
		add_action(
			'wp_head',
			static function (): void {
				echo '<meta name="robots" content="noindex">' . "\n";
			}
		);
		get_header();
		$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		?>
		<div class="fw-shared fw-shared--unavailable">
			<h1><?php esc_html_e( "This wishlist isn't available", 'flexa-woocommerce-wishlist' ); ?></h1>
			<p><?php esc_html_e( 'The link may have been changed or the list made private.', 'flexa-woocommerce-wishlist' ); ?></p>
			<a class="fw-btn-cta" href="<?php echo esc_url( (string) $shop_url ); ?>"><?php esc_html_e( 'Continue shopping', 'flexa-woocommerce-wishlist' ); ?></a>
		</div>
		<?php
		get_footer();
	}

	/**
	 * @param array<int,array<string,mixed>> $items
	 */
	private function head( Wishlist $list, array $items, bool $allow_index ): void {
		$store = get_bloginfo( 'name' );
		$title = sprintf(
			/* translators: 1: owner name, 2: store name. */
			__( '%1$s at %2$s', 'flexa-woocommerce-wishlist' ),
			$list->name,
			$store
		);
		$image = '';
		foreach ( $items as $item ) {
			if ( ! empty( $item['product']['image']['src'] ) ) {
				$image = (string) $item['product']['image']['src'];
				break;
			}
		}
		$url = home_url( add_query_arg( [] ) );

		add_action(
			'wp_head',
			static function () use ( $title, $image, $url, $allow_index ): void {
				echo '<meta property="og:type" content="website">' . "\n";
				echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
				echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
				if ( '' !== $image ) {
					echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
					echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
				}
				echo '<meta name="robots" content="' . ( $allow_index ? 'index,follow' : 'noindex,follow' ) . '">' . "\n";
			},
			1
		);

		// Core echoes the document title unescaped; escape here (defence in depth —
		// the list name is already sanitized on write).
		add_filter( 'pre_get_document_title', static fn (): string => esc_html( $title ) );
	}

	/**
	 * @param array<string,mixed> $item
	 */
	private function card( array $item ): string {
		if ( ! empty( $item['ghost'] ) || empty( $item['product'] ) ) {
			return '';
		}
		$p = $item['product'];

		return sprintf(
			'<li class="fw-card"><a class="fw-card__media" href="%1$s"><img src="%2$s" alt="%3$s" loading="lazy"></a>'
			. '<div class="fw-card__body"><a class="fw-card__title" href="%1$s">%3$s</a>'
			. '<div class="fw-card__price">%4$s</div></div></li>',
			esc_url( (string) ( $p['permalink'] ?? '' ) ),
			esc_url( (string) ( $p['image']['src'] ?? '' ) ),
			esc_html( (string) ( $p['name'] ?? '' ) ),
			wp_kses_post( (string) ( $p['priceHtml'] ?? '' ) )
		);
	}

	private function owner_line( Wishlist $list ): string {
		if ( ! (bool) Settings::get( 'sharing', 'show_owner_name' ) || $list->owner_user_id <= 0 ) {
			return __( "A customer's wishlist", 'flexa-woocommerce-wishlist' );
		}
		$user = get_userdata( $list->owner_user_id );
		$name = $user instanceof \WP_User ? $user->display_name : '';
		if ( '' === $name ) {
			return __( "A customer's wishlist", 'flexa-woocommerce-wishlist' );
		}
		/* translators: %s: customer display name. */
		return sprintf( __( "%s's wishlist", 'flexa-woocommerce-wishlist' ), $name );
	}
}
