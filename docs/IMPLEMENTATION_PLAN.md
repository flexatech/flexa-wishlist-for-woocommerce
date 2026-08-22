# Flexa Wishlist — Implementation Plan & Progress

Phased, core-first build tracked against `docs/PRODUCT_SPEC.md`. Naming binding: §0 of the spec (see also project memory `flexa-wishlist-vocabulary`).

## Status legend
✅ done · 🚧 in progress · ⬜ not started

## Phase 1 — PHP foundation (Agent A) ✅
- ✅ Bootstrap `flexa-wishlist-for-woocommerce.php` (constants, dual-tree autoloader, WC guard, HPOS declare, activation/deactivation).
- ✅ `Plugin` boot orchestration (Free then Pro by file presence).
- ✅ `Support\Settings` typed nested schema (~28 controls) with defaults / coerce / partial-merge sanitize.
- ✅ `Support\SingletonTrait`, `Support\Capabilities` (view=`manage_woocommerce`, settings=`manage_options`), `Support\Resetter`, `Support\Scheduler`.
- ✅ `Install\Migrator` (4 tables: lists / items / stock_subs / analytics), `Activator` (seed + auto-create page), `Deactivator`.
- ✅ `uninstall.php` honoring the delete-data setting.

## Phase 2 — Domain (Agent A) ✅
- ✅ `Domain\OwnerContext`, `Wishlist` + `WishlistRepository`, `Item` + `ItemRepository`.
- ✅ `Domain\Guest\GuestSession` (signed cookie, hash lookup, rolling TTL, no cookie before first interaction).
- ✅ `Domain\Merge\MergeService` (silent additive dedupe on `wp_login`, idempotent).
- ✅ `Domain\Product\ProductHydrator` (live WC data, ghost handling, never formats prices).
- ✅ `Domain\WishlistService` (state, add, snapshot capture, target-list resolution).
- ✅ `Domain\Analytics\DashboardRepository`.

## Phase 3 — REST (Agent A/D) ✅
- ✅ `BaseRestController` (envelope, permissions, owner resolution), `RegisterFacade`.
- ✅ Controllers: Settings, State, Lists, Items (add/toggle/remove/restore/update), Share, Admin.
- ✅ Multi-list mutations gated on `flexa_wishlist/pro/is_licensed`.

## Phase 4 — Storefront (Agent B) ✅
- ✅ `Frontend\Assets` (conditional enqueue, preset `--fw-*` vars, custom CSS), `ButtonRenderer` (cache-safe neutral markup), `CounterRenderer`, `PageRenderer` (client-hydrated), `Shortcodes`, `ShareView` (SSR + OG meta).
- ✅ `assets/frontend/flexa-wishlist.js` (hydration + optimistic toggle + page + shared + toast + `fw:` events).
- ✅ `assets/frontend/flexa-wishlist.css` (button/counter/page/cards/empty/toast, reduced-motion).

## Phase 5 — Integrations (Agent F) ✅
- ✅ `Integration\ShareRoute` (pretty `/wishlist/{slug}`), `Integration\Privacy` (GDPR exporter/eraser).
- ✅ `Cli\WishlistCommand` (reset/stats/cleanup — reset shares the Resetter path).
- ✅ composer.json, phpstan.neon + bootstrap, readme.txt.

## Phase 6 — Admin React app (Agent C) ✅
- ✅ Vite + React 18 + Tailwind v4 (`fw` prefix) scaffold under `apps/admin/`, mount `flexa-wishlist-admin-root`; builds to `assets/dist/` with manifest.
- ✅ Dashboard (totals + top products, Pro teaser) and Settings tabs (General/Appearance/Button/Wishlist Page/Sharing/Counter/Advanced + locked Notifications/Analytics teasers) mapping the schema; TanStack Query + Zustand; Toaster; DangerZone reset with typed confirm.
- ✅ `pnpm type-check` + `pnpm build` clean.

## Phase 7 — Blocks & Elementor (Agent F) ✅
- ✅ Gutenberg blocks (`flexa-wishlist/page` · `/button` · `/counter`) — `Integration\Blocks` dynamic blocks whose `render_callback`s delegate 1:1 to the shortcode renderers; no-build editor (`assets/blocks/editor.js`, ServerSideRender preview for page/counter, Placeholder for button).
- ✅ Elementor widgets (Page/Button/Counter) — `Integration\ElementorIntegration` boots on `elementor/loaded`; all `\Elementor\*` code isolated under `src/Integration/Elementor/` (bootstrap + 3 widgets) and excluded from PHPStan (no stubs). Same shortcode render path.

## Phase 8 — Cart bridge & add-all (Agent B) ✅
- ✅ `Domain\Cart\CartService` (add single/many to the Woo cart via `WC()->cart`, live re-validation → skip-reason codes: out_of_stock / needs_selection / unavailable / add_failed; honours `advanced.remove_after_add_to_cart`).
- ✅ `Rest\CartController` — `POST /cart/add` (one owned item) + `POST /cart/add-all` (whole list, per-item skip report); both return fresh wishlist `state`.
- ✅ Storefront: card add-to-cart + page "Add all to cart" toolbar (`PageRenderer`, gated on `page.add_to_cart`), AJAX add with `wc_fragment_refresh`, skip-summary toast, `fw:added-to-cart` / `fw:added-all` events.

## Phase 9 — Pro (`src-pro/`, separate build) ⬜
- ⬜ Multiple lists UI, visibility/public sharing, price-drop + back-in-stock jobs & emails, drawer, analytics, wishlist browser.

## Quality gates (all green this build)
- **phpcs: 0 errors / 0 warnings** (`composer lint` → `phpcs.xml.dist`). Ruleset = WordPress-Extra with the lineage's deliberate style encoded: short arrays + short ternary allowed, PSR-4 file names (WordPress.Files.FileName excluded), slash hooks (`/` word delimiter), prefixes (`flexa_wishlist`/`flexa_wl`/`FLEXA_WISHLIST`/`Flexa\Wishlist`), text-domain, PHP 8.2 compat. Three inline ignores, each with a reason: two versionless Vite dev-server enqueues, one local build-manifest `file_get_contents`.
- **PHPStan level 6: no errors** (`composer analyse`). `treatPhpDocTypesAsCertain: false` (WC stubs type `WC()->cart` non-null; keeps runtime null-guards honest). `src/Integration/Elementor/*` excluded (stub-less `\Elementor\*` base classes).
- **PHPUnit: 29 tests / 71 assertions green** (`composer test` → `phpunit.xml.dist`). Dependency-free unit suite (`tests/bootstrap.php` shims the handful of WP functions used) covering `OwnerContext`, `Item`, and the `Settings` schema (partial-merge, enum/int/bool coercion, unknown-key drop, colour + CSS sanitisers, sharing-channel intersection).
- `php -l` clean across all 50 PHP files; storefront + block editor JS parse (`node --check`); admin `type-check` + `build` clean.

## Known toolchain debt
- phpstan requires `composer install` (woocommerce-stubs) before it can resolve WC classes; run with `--memory-limit=1G`.
- `assets/dist` is git-ignored until the admin app is built; `Enqueue` guards on manifest presence.
- Tests are pure unit tests; repository / cart / REST integration tests (need `$wpdb` + WC) are a future suite.

## Verification (this build)
- `php -l` clean across all `src/` files (incl. new Cart, Blocks, Elementor) + bootstrap + uninstall.
- **PHPStan level 6: no errors** (`vendor/bin/phpstan analyse --memory-limit=1G`, WooCommerce + WP-CLI stubs scanned). `treatPhpDocTypesAsCertain: false` set (WC stubs type `WC()->cart` non-null; keeps runtime null-guards honest). `src/Integration/Elementor/*` excluded (stub-less `\Elementor\*` base classes).
- Storefront + block editor JS parse (`node --check`); admin app `type-check` + `build` clean; manifest emitted.

## Cart bridge notes
- `CartService::cart()` calls `wc_load_cart()` on demand (REST requests don't boot the cart) and never double-loads (that would replace the cart and drop items).
- Add-all pulls the whole list (cap 500) in one pass; per-item skip reasons are surfaced to the shopper via a toast.

## Acceptance mapping
§24 A–F + I1–I3 + J is the Free definition of done. Storefront flows implemented; unit tests cover the pure domain + settings logic (integration tests pending).
