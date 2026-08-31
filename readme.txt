=== Flexa Wishlist for WooCommerce ===
Contributors: flexatech
Tags: woocommerce, wishlist, wishlists, save for later, ecommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A mobile-first, cache-safe WooCommerce wishlist: one-tap save, guest persistence, silent account merge, and easy sharing.

== Description ==

Flexa Wishlist replaces the dated "table of products with social buttons" pattern with the wishlist UX shoppers already know from modern commerce apps.

* **One-tap save** — an optimistic heart button on product cards and pages. No modal, instant feedback.
* **Works for guests** — saved items persist without an account, and merge into the account silently and losslessly on login.
* **Cache-safe** — no per-user markup in cacheable pages; one small dependency-free script and one batched state request. Correct behind full-page caches.
* **A designed wishlist page** — a responsive card list with add-to-cart, remove-with-undo, and a proper empty state.
* **Add to cart from the wishlist** — move a single item or the whole list into the cart in one tap, with clear skip reasons for out-of-stock or variable products.
* **Easy sharing** — native share sheet on mobile, copy-link + channels on desktop, with correct link-preview meta.
* **Zero configuration** — beautiful defaults, presets over knobs; the accent color follows your theme out of the box.
* **Accessible & fast** — WCAG 2.2 AA, no color-only state, tight performance budgets.
* **Developer friendly** — REST API, action/filter hooks, `fw:` DOM events, Gutenberg blocks, shortcodes, and Elementor widgets.

Flexa Wishlist for WooCommerce is a complete, professional wishlist product — free.

= Placement =

Everything renders through three shortcodes (also available as Gutenberg blocks and Elementor widgets):

* `[flexa_wishlist]` — the full wishlist page.
* `[flexa_wishlist_button product_id="123"]` — a save button (defaults to the current product in a loop).
* `[flexa_wishlist_counter]` — a live saved-items count, ideal for the header/menu.

== Installation ==

1. Install and activate WooCommerce.
2. Upload the plugin, or install it from Plugins → Add New, then click Activate.
3. On activation a heart button appears on your products and a Wishlist page is created automatically.
4. Fine-tune under **Wishlist** in the admin menu (optional — the defaults are production-ready).

== Frequently Asked Questions ==

= Does it work with page caching? =
Yes. The button markup is user-agnostic; the shopper's real saved state is reconciled from a single request after load, so it stays correct behind full-page caches and CDNs.

= Do guests need an account? =
No. Guest wishlists persist via a first-party cookie and merge into the account automatically, and losslessly, on login.

= How do I show the wishlist somewhere specific? =
Use the `[flexa_wishlist]` shortcode on any page, the "Flexa Wishlist" Gutenberg block, or the Elementor widget. A wishlist page is also created for you on activation.

= Can I add a save button and a counter to my theme? =
Yes. Use `[flexa_wishlist_button]` (inside a product loop it targets the current product, or pass `product_id`) and `[flexa_wishlist_counter]` for a live count in your header or menu. Both are available as blocks and Elementor widgets too.

= Does it work with the block editor and Elementor? =
Yes. The page, button, and counter are all provided as native Gutenberg blocks and Elementor widgets that mirror the shortcodes.

= Is it accessible? =
Yes — WCAG 2.2 AA. Saved state is never communicated by color alone.

= Can shoppers add wishlisted products to the cart? =
Yes. Each item has an add-to-cart action, and the wishlist page offers "Add all to cart". Items that can't be added (out of stock, or variable products that need an option selected) are clearly skipped with a reason.

= Is it translation-ready? =
Yes. All strings use the `flexa-wishlist-for-woocommerce` text domain and a `.pot` template is included under `/i18n/languages`.

= What happens to my data when I uninstall? =
Uninstalling removes the plugin's tables and options so nothing is left behind. Deactivating alone keeps your data intact.

== Screenshots ==

1. The one-tap heart button on product cards, with instant optimistic feedback.
2. The wishlist page — a responsive card list with add-to-cart and remove-with-undo.
3. The admin dashboard — a quick pulse on how shoppers use their wishlists.
4. Settings — presets over knobs, with the accent color following your theme.
5. Sharing a wishlist — copy the link, use the native share sheet, or post to social channels.

== Source code ==

The plugin ships a compiled admin interface (`assets/dist/`) built from the
human-readable source in `apps/admin/src/`. The full source and build tooling
are also public at:

https://github.com/flexatech/flexa-wishlist-for-woocommerce

The admin app is built with pnpm and Vite:

1. `pnpm install`
2. `pnpm run build`   (or `pnpm run dev` for a watched build)

== Changelog ==

= 1.1.0 =
* New: Share button on the wishlist page — generates a stable share link with copy-to-clipboard, the native share sheet, and the social channels enabled in Settings.

= 1.0.2 =
* Fix: wishlist page loading spinner stayed visible after items loaded; it now hides once the list (or empty state) renders.

= 1.0.1 =
* Removed all Pro/licensing gates — multiple wishlists and every feature are now free.
* Security: verify ownership on item toggle to prevent cross-account removal (IDOR).
* Removed the custom CSS field; appearance is driven entirely by validated presets.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.0 =
Adds a Share button to the wishlist page with copy-link, native share, and social channels.

= 1.0.2 =
Fixes the wishlist page spinner that could keep spinning after items loaded.

= 1.0.1 =
Security and compliance fixes: multi-wishlist unlocked for free, item-toggle ownership check, custom CSS removed.

= 1.0.0 =
Initial release of Flexa Wishlist for WooCommerce.
