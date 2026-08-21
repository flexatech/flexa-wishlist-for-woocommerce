# Flexa WooCommerce Wishlist — Product + UX + Functional Specification

> **Status:** Final product specification, v1.1 — 2026-08-21 (all §26 open questions resolved by product owner; implementation-ready)
> **Author role:** Lead Product Designer / UX Architect / Product Strategist
> **Audience:** AI coding agents and human developers implementing the plugin
> **Rule:** This document contains **no implementation code**. It defines what to build, why, and how it must behave. Implementation details not constrained here are developer freedom (see §23 and §57-style boundaries in §23.4).
>
> **Companion references (read before implementing):**
> - Flexa architecture conventions: skill `flexa-plugin-conventions` (PHP/TS lineage rules)
> - Flexa admin design system: skill `flexa-plugin-ui` (React admin app rules)
> - Reference sibling plugin: `../flexa-extra/` (bootstrap, settings, REST, admin app shape)
> - Competitive baseline: `../yith-wishlist-features.md`

---

## 0. Naming & Convention Binding (locked)

These values are final. They follow the Flexa lineage rules and must not be re-invented.

| Token | Value |
|---|---|
| Plugin folder / slug / text-domain | `flexa-woocommerce-wishlist` |
| Display name | **Flexa Wishlist for WooCommerce** |
| PHP namespace (Free) | `Flexa\Wishlist\` → `includes/` (or `src/` — match scaffold) |
| PHP namespace (Pro) | `Flexa\WishlistPro\` → `src-pro/` |
| Constants prefix | `FLEXA_WISHLIST_` (`_VERSION`, `_FILE`, `_PLUGIN_DIR`, `_PLUGIN_URL`, `_BASE_NAME`, `_REST_NAMESPACE`, `_IS_DEVELOPMENT`) |
| REST namespace | `flexa-wishlist/v1` |
| Hook prefix | `flexa_wishlist/` — slash-separated: `flexa_wishlist/{domain}/{event}` |
| Option prefix | `flexa_wishlist_` (single settings option: `flexa_wishlist_settings`) |
| Admin JS global | `window.flexaWishlist` |
| Storefront JS global | `window.flexaWishlistFront` |
| Admin React mount id | `flexa-wishlist-admin-root` |
| Tailwind prefix (admin app) | `fw` |
| Theme wrapper class (admin app) | `.flexa-wishlist-themed` |
| Zustand persist key | `flexa-wishlist:ui` |
| Storefront DOM/event prefix | `fw-` classes, `fw:` CustomEvents (e.g. `fw:item-added`) |
| Admin menu slug | `flexa-wishlist`, top-level menu position ~80, capability `manage_woocommerce` for viewing, `manage_options` for settings |

Architecture follows the Flexa lineage exactly: singleton services booted from `Initialize`, `Support\Settings`-style typed option schema with sanitizer + partial-merge save, `BaseRestController` with real `permission_callback` on every route, all SQL inside `Domain/*Repository` classes, one shared `Support\Resetter` for the REST danger-zone and WP-CLI, Pro presence by file existence gated by `flexa_wishlist/pro/is_licensed`.

---

# 1. Executive Summary

Flexa Wishlist for WooCommerce is a premium, mobile-first wishlist platform for WooCommerce stores. It replaces the decade-old "wishlist = a table of products with social buttons" pattern (YITH et al.) with the wishlist UX shoppers already know from modern commerce apps: **one tap to save, instant feedback, a beautiful saved-items page, effortless sharing, and quiet intelligence** (price-drop and back-in-stock awareness) — all without configuration.

Why it is better:

1. **Zero-config excellence.** Activation alone produces a polished heart button on product cards and pages, a designed wishlist page, a working guest wishlist, and a correct guest→account merge. YITH requires an hour in settings to look acceptable; Flexa looks right in minute one.
2. **One-tap add, never a modal.** The primary action is always a single optimistic tap into the default list. Multiple lists (Pro) are reached through a lightweight follow-up ("Change") — never forced on shoppers who just want a heart.
3. **Cache-safe, near-zero-cost frontend.** No server-rendered per-user markup in cacheable pages, one small dependency-free script, one batched state call. Works correctly behind full-page caches where competitor buttons show stale state.
4. **Merchant intelligence, not vanity metrics.** The admin answers three questions: what do people want, is the wishlist converting, and which demand is blocked (out of stock / price-sensitive) — each tied to an action.
5. **A commercially honest Free/Pro split.** Free is a complete, professional single-wishlist product. Pro adds the power layer: multiple lists, visibility & rich sharing, price tracking, back-in-stock alerts, the wishlist drawer, analytics, and promotional tools.

---

# 2. Product Vision

**"Saving something for later should be the easiest thing in the store."**

Principles (binding on all design and implementation decisions):

1. **Excellent defaults** — beautiful with zero configuration; presets over knobs.
2. **Progressive disclosure** — one heart for everyone; lists, sharing, tracking only when reached for.
3. **Customer first** — every decision starts from the shopper moment, not the settings screen.
4. **Mobile first** — bottom sheets, thumb-reach actions, no compressed desktop tables.
5. **Performance first** — every request, script, and query must justify itself (§17).
6. **Accessibility first** — WCAG AA; state never communicated by color alone (§15).
7. **Flexa identity** — same design language, admin architecture, and quality bar as sibling Flexa plugins.
8. **Feature quality over feature count** — fewer, finished features; §27 lists what we deliberately do not build.

---

# 3. Target Users

## Shopper personas

| Persona | Description | Primary needs |
|---|---|---|
| **The Browser** (majority) | Casually saves items while browsing, mostly on mobile. May never log in. | One-tap save, persistence without an account, easy return path (counter/menu), zero friction. |
| **The Planner** | Researches considered purchases (furniture, electronics). Compares, waits for price/stock. | Reliable persistence, variation capture, price-drop awareness, stock alerts. |
| **The Gifter** (Pro) | Builds lists for occasions (birthday, wedding, holidays) and shares them. | Multiple named lists, clean shared view, link sharing, privacy control. |
| **The Loyal Customer** | Logged-in repeat buyer; wishlist is their personal catalog. | Fast wishlist page, add-to-cart from list, quantity, move/organize (Pro). |

## Merchant personas

| Persona | Description | Primary needs |
|---|---|---|
| **Solo store owner** | Non-technical; installs, expects it to work. | Zero-config quality, presets, simple settings, theme compatibility. |
| **Growing store / marketer** | Wants wishlist as a conversion and retention channel. | Analytics, price-drop & back-in-stock emails, popular-products insight, campaign hooks. |
| **Agency / developer** | Builds stores for clients; needs control and extensibility. | Blocks/shortcodes/Elementor, hooks & REST, template overrides, custom CSS escape hatch, clean data model. |

---

# 4. Jobs To Be Done

Ranked; MVP must nail Jobs 1–4.

| # | Job (shopper) | Priority |
|---|---|---|
| 1 | "When I see something I like but am not ready to buy, I want to save it in one tap so I can find it later." | P0 |
| 2 | "When I come back (same device, even weeks later, even without an account), I want my saved items still there." | P0 |
| 3 | "When I'm ready, I want to move saved items into the cart without re-finding them." | P0 |
| 4 | "When I create an account or log in, I want my saved items to follow me — nothing lost." | P0 |
| 5 | "When a saved item gets cheaper or comes back in stock, I want to know." | P1 (Pro) |
| 6 | "When I'm planning an occasion, I want separate named lists and to share one with others." | P1 (Pro) |
| 7 | "I want to tidy my list — remove, move, adjust intended quantity." | P1/P2 |

| # | Job (merchant) | Priority |
|---|---|---|
| 1 | "I want shoppers to save intent so they return instead of forgetting my store." | P0 |
| 2 | "I want to see which products people want most, so I can stock/promote them." | P1 |
| 3 | "I want wishlist intent to convert — nudges when price drops or stock returns." | P1 (Pro) |
| 4 | "I want it to look native to my theme without hiring a developer." | P0 |

---

# 5. Competitive Analysis

Baseline: YITH WooCommerce Wishlist v4.17 (free + premium claims), plus TI Wishlist, modern platform UX (Shopify apps, Amazon, ASOS, Zalando-class saved-items experiences).

**Where incumbents are strong (must match):** feature breadth (placement options, shortcodes/Elementor, sharing channels, template overrides, GDPR exporter/eraser, translations); premium concepts (multiple lists, visibility, quantity, price tracking, estimates).

**Where incumbents are weak (our opening):**

1. **Default look is dated.** Table-based wishlist page, icon-and-link styling that needs manual color work. Mobile is a squeezed table.
2. **Interaction cost.** Popups/modals for multi-list add; full page reloads in places; feedback via page notices instead of instant state change.
3. **Cache correctness.** Server-rendered per-user button state breaks behind page caches; their AJAX-load option exists precisely because the default is broken — we make cache-safe the only mode.
4. **Settings overload.** Dozens of per-element color pickers and label fields; the merchant does the designer's job.
5. **Analytics as an afterthought.** "Popular products" report with no action attached.

**Classification (per §47 of the brief):**

- **Match:** add/remove/browse, guest + logged-in persistence, variation support, loop & product-page placement, shortcodes + blocks + Elementor, share channels, GDPR, i18n/WPML, REST, template/hook extensibility, add-to-cart behaviors.
- **Improve:** wishlist page (cards, not tables), multi-list add UX (toast + popover, not modal), sharing (copy-link + native share first), guest merge (deterministic, additive), feedback (optimistic UI), admin (task-oriented, presets), analytics (actionable), cache-safety.
- **Avoid:** per-element color-picker farms, promotional email builders inside the plugin (integrate instead), "ask for an estimate" (niche B2B — future), public wishlist search/directory, drag-and-drop reorder in MVP.
- **Differentiate:** zero-config polish, price-drop intelligence surfaced *in the UI* (not just email), demand-based admin insights ("most wanted, out of stock" list), first-class Web Share / QR, wishlist drawer, blocks-native building.

---

# 6. Product Differentiation

Why Flexa wins, in one line each:

1. **Instant everywhere** — optimistic heart, no reloads, no modals, cache-safe state hydration.
2. **Designed, not configured** — presets (Flexa / Minimal / Classic) + theme-token inheritance instead of 40 color settings.
3. **The wishlist is a page shoppers actually like** — commerce-grade card layout, designed empty state, sticky mobile actions.
4. **Quiet intelligence** — "Added at $129 · Now $99 ↓ $30" badges, notify-me on out-of-stock saved items (Pro).
5. **Merchant answers, not dashboards** — three actionable insight blocks (Pro analytics).
6. **Flexa family** — same admin design system, REST/hook discipline, and code quality as other Flexa plugins; agencies can trust the pattern.

### "Wow" moments (design targets, each individually specified later)

1. First tap on the heart: icon fills with a subtle scale "pop", toast "Added to Favorites — View · Change" (§10.2).
2. Guest returns days later: wishlist intact, counter correct, no login ever demanded (§9 Flow 1, §10.8).
3. Login after guest browsing: silent additive merge + one reassuring toast (§9 Flow 9).
4. The wishlist page itself: looks like a curated collection, not an admin table (§10.4).
5. Price-drop badge on a saved item (§19.2).
6. Share: one tap → native share sheet on mobile; copy-link-first popover on desktop (§20).
7. Add all to cart with smart skip summary (§10.6).
8. Empty state that invites shopping, with recently-viewed suggestions (§10.7).
9. The multi-list "Change" popover — three lists and "New list" in a bottom sheet, two taps total (§10.3).
10. Merchant opens settings and thinks "that's it? great." (§11).

---

# 7. Information Architecture

## 7.1 Frontend (shopper)

```
Storefront
├── Product card (loop: shop/category/search/sliders)  → WishlistButton (icon overlay)
├── Single product page                                → WishlistButton (labelled, near add-to-cart)
├── Header (theme-dependent)                           → WishlistCounter (icon + count → wishlist page)
├── Wishlist page (/wishlist, auto-created)
│   ├── Owner view: items, actions, share, (Pro) list switcher
│   ├── Empty state
│   └── Shared view (visitor via share link — read-only + add-to-cart/copy)
├── Wishlist drawer (Pro)                              → slide-over/bottom-sheet quick view
└── My Account → "Wishlist" menu item                  → links to wishlist page
```

## 7.2 Admin (merchant)

React SPA (hash-routed) under a top-level **Flexa Wishlist** menu, following the `flexa-extra` header-nav pattern:

```
Flexa Wishlist (admin SPA)
├── Dashboard        /            KPIs + top wishlisted + attention list (free: basic; Pro: full)
├── Wishlists        /wishlists   Browse/search wishlists & items (Pro; Free shows teaser + counts)
├── Analytics        /analytics   Trends & conversion (Pro)
└── Settings         /settings    Tabs: General · Appearance · Wishlist Page · Sharing ·
                                  Notifications (Pro) · Advanced
```

Settings philosophy: ≤ ~30 total controls across all tabs (§11.4). Everything else is presets, hooks, or custom CSS.

---

# 8. Feature Matrix

Legend: **MVP** = Free launch scope. **PRO** = paid. **ADV** = Pro, larger-store value. **FUT** = do not build yet. Priority P0–P3. Complexity S/M/L.

## Core & placement

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| One-tap add/remove (optimistic) | MVP | P0 | M | The product. |
| Default wishlist auto-created ("Favorites") | MVP | P0 | S | §10.1 |
| Guest wishlist (server-persisted, cookie token) | MVP | P0 | M | §9.1 |
| Guest→account merge on login/registration | MVP | P0 | M | §9 Flow 9 |
| Variation capture (selected variation saved) | MVP | P0 | M | §16.2 |
| Button on single product page | MVP | P0 | S | Hook-placed, position setting |
| Button on loop/product cards | MVP | P0 | M | Overlay icon default |
| Shortcodes (button, page, counter) | MVP | P0 | S | Parity + builders |
| Gutenberg blocks (button, page, counter) | MVP | P1 | M | §10.10 |
| Elementor widgets (button, wishlist, counter) | MVP | P1 | M | §10.10 |
| Wishlist counter (block/shortcode + auto-inject attempt) | MVP | P1 | S | §10.9 |
| My Account menu entry | MVP | P1 | S | |

## Wishlist page

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| Card-list layout (default) + grid alternate | MVP | P0 | M | §10.4; no table |
| Per-item add-to-cart (variation-aware) | MVP | P0 | M | §10.5 |
| Add all to cart (smart skip) | MVP | P1 | M | §10.6 |
| Remove item (with undo toast) | MVP | P0 | S | |
| Designed empty state | MVP | P0 | S | §10.7 |
| Pagination / incremental load (24/page) | MVP | P1 | S | §17 |
| Stock status + price display | MVP | P0 | S | Live values, not snapshots |
| Date added (secondary text) | MVP | P2 | S | |
| Item note (private, short) | FUT | P3 | S | Cut from MVP — low usage evidence |
| Sort (date added default; price, name) | ADV | P2 | S | |
| Drag-and-drop reorder | FUT | P3 | M | Sort covers the job |
| Related products on wishlist page | FUT | P3 | M | Distraction from conversion |

## Multiple lists, visibility, sharing

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| Share default wishlist via link (revocable) | MVP | P1 | M | Free gets link sharing; §20 |
| Copy link + native Web Share + email/WhatsApp/X/Facebook/Pinterest | MVP | P1 | S | Popover, not button wall |
| Social meta (OG title/description/image) for shared view | MVP | P2 | S | |
| Multiple wishlists (create/rename/delete/switch/set default) | PRO | P0 | L | §10.3 |
| Add-to-specific-list flow ("Change" popover) | PRO | P0 | M | §10.3 |
| Move / copy items between lists | PRO | P1 | M | |
| Per-list visibility: Private / Shared-link / Public | PRO | P1 | M | §20.2 |
| QR code for share link | FUT | P3 | S | |
| Public wishlist search/directory | FUT | P3 | L | Privacy-heavy, low demand |

## Intelligence & notifications

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| Price snapshot at add time | MVP | P1 | S | Data now, UI later — enables Pro |
| Price-change badge on wishlist items | PRO | P1 | M | §19.2 |
| Price-drop email notification (consented) | PRO | P1 | L | §19 |
| Back-in-stock "Notify me" on saved OOS items | PRO | P1 | L | §19.3 |
| Quantity (desired qty per item) | PRO | P2 | S | Off by default; §10.5 |
| Promotional email campaigns | FUT | P3 | L | Integrate with email tools instead |
| "Ask for an estimate" / quote requests | FUT | P3 | L | Niche B2B |

## Admin

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| Dashboard: total wishlists, items saved, top 5 products | MVP | P1 | M | Free = basic counts |
| Settings (tabs + presets) | MVP | P0 | M | §11.4 |
| Appearance presets (Flexa/Minimal/Classic/Custom) | MVP | P0 | M | §11.5 |
| Custom CSS box | MVP | P2 | S | Escape hatch |
| Wishlist browser (search users/products/lists, view detail) | PRO | P1 | M | Read-only in v1; §11.3 |
| Analytics (trends, conversion funnel, attention list) | PRO | P1 | L | §11.2 |
| CSV export (top products, wishlist emails w/ consent) | ADV | P2 | M | |
| Danger zone: reset all data (typed confirm) | MVP | P1 | S | Shared Resetter path |

## Platform

| Feature | Tier | Pri | Cx | Notes |
|---|---|---|---|---|
| REST API (§21) | MVP | P0 | M | Storefront runs on it |
| PHP hooks + JS CustomEvents (§22) | MVP | P0 | S | |
| GDPR exporter/eraser + retention (§18) | MVP | P0 | M | |
| i18n + RTL (§40 brief → §15/§14 here) | MVP | P0 | S | |
| WP-CLI (stats, reset) | ADV | P2 | S | |
| Template overrides for shared/wishlist page markup | ADV | P2 | M | Filterable markup first; full template system later |

---

# 9. User Flows

Conventions used below: every flow lists Entry → Trigger → UI → State → Success → Errors → Exit. "Toast" = the storefront toast component (§12, WishlistNotice), auto-dismiss ~3.5s, polite `aria-live`.

## 9.1 Flow 1 — Guest adds a product

- **Entry:** Guest on product card or product page. Button shows ♡ "Add to wishlist" (label on product page; icon-only with accessible name on cards).
- **Trigger:** Click/tap the button.
- **UI:** Icon fills ♡→♥ instantly (optimistic) with a ~150ms scale pop (suppressed under reduced motion). Toast: "**Added to Favorites** — View wishlist". Counter increments.
- **State:** If no guest token exists, the client requests one implicitly via the add call; server creates a guest wishlist bound to a signed, HttpOnly-where-possible cookie token (§18.1), 30-day rolling expiry, refreshed on each interaction. Item persisted (product, variation if selected, price snapshot, timestamp). Local state cache (localStorage) updated for instant paint on future cached pages.
- **Success:** Button state persists across pages and revisits on the same browser.
- **Errors:** Request fails → revert icon to ♡, toast "Couldn't save. Please try again." with a Retry action; no partial state left behind.
- **Exit:** Continue browsing, or "View wishlist" → wishlist page.

## 9.2 Flow 2 — Logged-in user adds a product

Same as Flow 1, but the item is written to the user's default wishlist; no cookie token involved. If the item is already in the wishlist, the button renders ♥ "In wishlist" on hydrate, and clicking it removes (toggle) with toast "Removed from Favorites — Undo".

### Decision — the button is a toggle
**Why:** One control, one mental model; matches every modern saved-items UX. **Alternatives:** click-again opens the wishlist (YITH "Browse wishlist" pattern) — rejected: surprising, and removal becomes hard to find. **Recommendation:** toggle with Undo in the removal toast; "View wishlist" lives in the add toast and counter.

## 9.3 Flow 3 — User adds to a different list (Pro)

- **Entry:** Pro active, user has ≥1 list. **Trigger:** Tap heart (adds to default, per Flow 1/2) → toast shows third action: "**Added to Favorites** — View · **Change**".
- **UI:** "Change" opens WishlistSelector: popover anchored to the toast/button on desktop; bottom sheet on mobile. Contents: list of wishlists (name + item count, current one checked), "+ New list" row. Selecting another list *moves* the just-added item there; toast updates: "Moved to Birthday".
- **State:** Item's wishlist id changes; both list counts update. The chosen list becomes the "last used list" and subsequent quick-adds this session target it (sticky target), indicated in future toasts ("Added to Birthday — View · Change").
- **Errors:** Move fails → item stays in default list, error toast with retry.
- **Exit:** Selector dismisses on choice, Esc, or outside tap.

### Decision — add-then-move ("post-add correction"), never a pre-add chooser
**Why:** The 90% case (default list) stays one tap; the chooser costs nothing unless invoked; no modal ever blocks the primary action. **Alternatives:** long-press/hover split-button to pre-choose (undiscoverable, no hover on touch); always-ask modal (YITH premium pattern — high friction). **Recommendation:** post-add "Change" + sticky last-used list.

## 9.4 Flow 4 — User creates a wishlist (Pro)

- **Entry:** "+ New list" in WishlistSelector, or "New list" button on wishlist page list switcher.
- **Trigger/UI:** Inline single-field form (bottom sheet on mobile, small dialog on desktop): name (required, ≤ 60 chars), visibility selector (Private default). Create button disabled until name non-empty.
- **State:** List created; if invoked from the Change flow, the pending item moves into it immediately.
- **Errors:** Duplicate name allowed (disambiguated by count); empty name blocked inline; server failure → inline error, form stays open with input preserved.
- **Exit:** Returns to originating context with new list selected.

## 9.5 Flow 5 — User moves an item (Pro)

- **Entry:** Wishlist page, item's "⋯ More" menu → "Move to…" (or "Copy to…").
- **UI:** Same WishlistSelector component. On confirm, item animates out of the current view; toast "Moved to Birthday — Undo".
- **State:** Item re-parented (move) or duplicated (copy; dedupe rule §10.3 applies).
- **Errors:** Failure → item stays, error toast.

## 9.6 Flow 6 — User adds a wishlist item to cart

- **Entry:** Wishlist page item. **Trigger:** "Add to cart" on the item.
- **UI:** Button → spinner (≥300ms min to avoid flicker) → "Added ✓". Toast "Added to cart — View cart". Item remains in the wishlist (default).
- **State:** WooCommerce cart updated via store AJAX; wishlist unchanged unless "remove after add-to-cart" setting is on (off by default). `flexa_wishlist/item/added_to_cart` fires (conversion tracking).
- **Variants:** Parent-level variable item → button reads "Select options" and links to the product page with the wishlist as return context (§16.2). Out of stock → button disabled, replaced by stock status (+ "Notify me" if Pro back-in-stock enabled). External product → "Buy at {vendor}" link, opens per product settings.
- **Errors:** Cart error (e.g. sold out between render and click) → toast with the store's error message; item row refreshes its live stock state.

### Decision — stay on the wishlist after add-to-cart; keep the item
**Why:** The wishlist is a working surface; shoppers often add several items. Removing on add destroys the "planner" job (re-purchase, comparison). **Alternatives:** redirect to cart (kills multi-add), auto-remove (data loss feel). **Recommendation:** stay + keep, both behaviors available as settings ("After add to cart: stay/go to cart", "Remove item after adding to cart: off/on").

## 9.7 Flow 7 — Add all to cart

- **Entry:** Wishlist page header action "Add all to cart" (visible when ≥2 purchasable items).
- **UI:** Button → progress state ("Adding 4 items…") → summary toast: "3 items added to cart. 1 item needs options — review below." Non-addable items (parent variables, OOS, external) are skipped, never blocking, and briefly highlighted.
- **State:** Sequential/batched cart additions; respects per-item quantity (Pro).
- **Errors:** Partial failure reported in the summary; nothing silently dropped.

## 9.8 Flow 8 — Guest logs in (no prior account wishlist)

Guest token wishlist is attached to the account as its default wishlist (rename to default title if unnamed). Cookie token invalidated. Toast on next page: "Your wishlist was saved to your account."

## 9.9 Flow 9 — Guest wishlist merges with existing account wishlist

- **Entry:** Guest with N items logs into an account that already has a wishlist.
- **Behavior (deterministic, must not be left to the implementer):**
  1. Every guest item is added into the account's **default** wishlist.
  2. Dedupe key = product id + variation id: if the account list already has the item, keep the account item (earliest `date_added` wins; keep account price snapshot).
  3. Nothing is ever deleted or replaced; merge is purely additive.
  4. Guest wishlist row and cookie token are destroyed after successful merge.
  5. Merge runs server-side on the login/registration hooks, idempotently (safe to re-run).
- **UI:** No interstitial, no prompt. One toast on the next rendered page: "Your saved items were added to your wishlist." Counter reflects merged total.

### Decision — silent additive merge, no user prompt
**Why:** There is no scenario where a shopper wants saved items discarded; asking creates anxiety and a decision with a wrong answer. Additive + dedupe is lossless. **Alternatives:** prompt "keep/discard/merge" (YITH-style ambiguity), guest list becomes a second list (clutters Pro lists, confusing in Free). **Recommendation:** silent additive merge into default list, exact rules above.

## 9.10 Flow 10 — User shares a wishlist

- **Entry:** Wishlist page header "Share" button (owner only).
- **UI:** First use: small confirm inline in the share popover — "Anyone with the link can view this list. [Create link]" (visibility → Shared). Then the share surface (§20): mobile = native share sheet via Web Share API with the link; desktop = popover with Copy link (primary, full-width), then Email / WhatsApp / X / Facebook / Pinterest icon row. "Link copied ✓" inline feedback.
- **State:** Unguessable share slug generated once (regenerable). `flexa_wishlist/list/shared` fires.
- **Errors:** Clipboard API failure → selectable text field fallback.
- **Exit:** Popover dismisses; visibility chip on the page header now reads "Shared".

## 9.11 Flow 11 — User changes visibility (Pro; Free = Private/Shared only)

Wishlist page header visibility chip → popover with radio options: **Private** (only you), **Shared** (anyone with the link), **Public** (link + listed on your profile page if the store enables it — see §20.2). Switching to Private immediately invalidates nothing but hides: the share link keeps existing but returns the "not available" screen until re-shared. "Reset link" action regenerates the slug (hard revoke). Copy explains each option in one line.

## 9.12 Flow 12 — Price-drop notification (Pro)

Trigger: daily price scan finds current price < snapshot by ≥ threshold (default 5%, setting). If the owner has consented to wishlist emails (§18.4): one digest-style email per user per day max — "1 item in your wishlist dropped in price", product card, old→new price, CTA to wishlist. In-UI: the item shows the price-drop badge regardless of email consent (badge needs no consent). States: unchanged / dropped / increased (badge only for dropped by default; "show increases" off). Unsubscribe link in every email flips consent off (§18.4).

## 9.13 Flow 13 — Back-in-stock notification (Pro)

- **Entry:** Wishlist item that is out of stock shows "Notify me when available" (guest: requires email entry + consent checkbox; logged-in: one click, uses account email, consent recorded).
- **States:** Not subscribed → Subscribed ("We'll email you ✓", with Unsubscribe) → Notification sent (subscription completes; badge "Back in stock" shows on the item) → item purchasable again.
- **Rules:** Subscription is per product/variation + email; one notification per restock event; notify job triggered by stock-status transition hooks with a queued sender (batch-safe for popular products). This is notification infrastructure that the wishlist *surfaces* — it must be modeled independently of wishlist items (deleting the item does not silently kill an active subscription; unsubscribing is explicit).

## 9.14 Flow 14 — Product/variation becomes unavailable

- Product deleted/trashed/hidden/private → item renders in a degraded "ghost" card: last-known name + image (from snapshot), "No longer available" label, only action = Remove. Never a broken layout or fatal error. Hidden/private products behave as unavailable to non-authorized viewers.
- Variation deleted or disabled → item falls back to parent product context: "This option is no longer available", CTA "Choose another option" → product page; add-to-cart disabled.
- Live data (price/stock) always re-resolved at render; snapshots are only for price comparison and ghost display.

## 9.15 Flow 15 — Empty wishlist

See §10.7. Entry via counter, account menu, or direct URL with zero items. Never a dead end.

---

# 10. Frontend UX Specification

## 10.1 The wishlist model (conceptual)

- **User** (WordPress account) or **Guest** (signed cookie token, §18.1) — each owns wishlists.
- **Wishlist**: id, owner (user id XOR guest token), name, is-default flag, visibility (private/shared/public), share slug, timestamps. Every owner has exactly one default wishlist, lazily created on first add. Default title: **"Favorites"** (translatable, merchant-editable label setting).
- **Wishlist item**: wishlist ref, product ref, optional variation ref + attribute summary, desired quantity (default 1; UI Pro-only), date added, price snapshot (amount + currency at add time), position.
- **Free tier**: exactly one wishlist per owner (the default). All flows/components below are designed so Pro's multiple lists slot in without re-architecture (list switcher, selector, move actions appear; nothing changes shape).
- Relationships: Owner 1→N Wishlists (Free: 1→1) · Wishlist 1→N Items · Item →1 Product (+0..1 Variation). Back-in-stock subscriptions (Pro) are a separate entity keyed by email + product/variation, merely linked from wishlist UI (§9.13).

## 10.2 WishlistButton (product page & cards)

The single most important component.

- **Product page (default placement):** labelled button — ♡ icon + "Add to wishlist" — rendered after the add-to-cart button (setting: after add-to-cart / before add-to-cart / after summary / off + shortcode/block manual). Secondary visual weight: outline/ghost style so it never competes with Add to cart.
- **Loop/cards (default placement):** icon-only circular button (44×44px target, 40px visual) overlaid top-right of the product image (setting: overlay top-right / under title / off). Accessible name always present ("Add {product} to wishlist" / "Remove {product} from wishlist").
- **States:** see State Matrix §13. Key behaviors: optimistic toggle; filled vs outline icon plus label change (never color alone); brief scale pop on add (respect `prefers-reduced-motion`); while a request is in flight the control stays interactive-looking but ignores duplicate clicks (last-write-wins queue).
- **Variable products:** on the product page the button captures the currently selected variation if valid, else the parent (§16.2). On cards it always saves the parent.
- **Cache-safety rule (binding):** server renders the button in a neutral "unknown" visual (outline heart, not disabled); the storefront script hydrates real state from one batched state call/local cache before or immediately after first paint. No per-user markup may be server-rendered into cacheable pages.

## 10.3 Multiple lists (Pro) — selector & switcher

- **WishlistSelector** (the "Change"/"Move to" surface): desktop = anchored popover (max ~320px, list rows: name, count, check on current; footer "+ New list"); mobile = bottom sheet with drag handle, same content, larger rows. Full keyboard support (arrow keys, Enter, Esc), focus trapped in sheet.
- **Sticky target:** last list explicitly chosen becomes the session's quick-add target (per §9.3). The toast always names the target list, so state is never hidden.
- **List switcher (wishlist page):** horizontal chips (mobile: scrollable) or a compact dropdown when >5 lists: list name + count; trailing "+ New". Current list's header exposes rename (inline edit), visibility chip, share, delete (confirm dialog; deleting a non-empty list offers "move items to Favorites" or "delete items"; the default list cannot be deleted, only renamed).
- **Copy vs move:** move = re-parent; copy = duplicate; both dedupe per-list on product+variation (copying an existing item is a no-op with informational toast).

## 10.4 Wishlist page layout

### Decision — responsive card list default; grid alternate; no table
**Why:** Cards carry image-led commerce content well, collapse naturally to mobile, and leave room for badges (price drop, stock) without column budgeting. Tables are the incumbent's dated pattern and fail on mobile. **Alternatives:** table (rejected: mobile, dated), grid-only (rejected: weak scanability for actions/metadata on desktop). **Recommendation:** card list default on all viewports; grid as a merchant-selectable alternate; table not built (non-goal).

- **Card list (default):** each item = horizontal card — image (~96px desktop / 80px mobile), then title (link), variation summary line, price line (current; sale styling; Pro price-drop badge), stock status line when not "in stock", date added (muted, small), then right-aligned action column: Add to cart (primary, compact), heart/remove, "⋯" menu (Move/Copy [Pro], Remove). Mobile: actions become a bottom row inside the card; "⋯" opens a bottom sheet.
- **Grid (alternate):** standard product-card grid (2-col mobile → 4-col desktop) with wishlist actions on-card; metadata reduced (no date).
- **Page header:** wishlist name (+ count), then: Share, Add all to cart, (Pro) list switcher + visibility chip, (ADV) sort control.
- **Shared view (visitor):** same card list, read-only — no remove/move; actions = Add to cart and a header "Save all to my wishlist" (copies items into the visitor's own default list, deduped). Owner attribution shown as first name/display name only (§18.5). Robots `noindex` unless visibility = Public.
- **Pagination:** 24 items/page; "Load more" button (not infinite scroll — footer must stay reachable); page state in URL for back-button correctness.

## 10.5 Item actions

- **Add to cart:** per §9.6, variation-aware; quantity stepper next to it only when Pro quantity is enabled (compact − 1 +, min/max/step respect product rules; changes persist debounced with inline saving tick).
- **Remove:** immediate optimistic removal with toast "Removed — Undo" (undo restores with original date). No confirm dialog for single-item removal.
- **Quantity is "desired quantity":** it prefills add-to-cart amount; it is not a reservation. UI hidden entirely unless the merchant enables it (default off) — most stores don't need it (Principle: no UI without value).

## 10.6 Add all to cart

Per §9.7. Additional rules: honors quantity; maintains item order; if *everything* was skipped, the toast explains why ("These items need options before they can be added"); never navigates away (respects the stay-on-wishlist decision, including when "after add to cart: go to cart" is set — that setting applies to single adds only; the summary toast links to cart).

## 10.7 Empty state

Designed, not apologetic: soft illustrated heart mark (Flexa illustration style, tintable to preset accent) · headline "Save what you love" · one line "Tap the ♡ on any product and it'll wait for you here." · primary CTA "Start shopping" (→ shop page; filterable) · below, "Recently viewed" product row when WooCommerce's recently-viewed data exists (max 4, no extra queries when absent). Shared-view empty state: "This list is empty" + browse CTA. Merchant customization: headline/body via settings? **No** — translatable strings + filter hook only (settings restraint).

## 10.8 Counter

`WishlistCounter`: heart icon + numeric badge → links to wishlist page. Provided as block, shortcode, Elementor widget, and a best-effort auto-injection into common theme header hooks (setting, **default OFF** — opt-in; graceful no-op when no hook exists — never DOM-hack themes). The block/shortcode/widget are the primary placement paths; auto-inject is a convenience for merchants who ask for it. States: empty (icon only, no "0" badge), count 1–99, "99+"; hydrates with the same batched state call (never blocks paint; badge fades in). Fully focusable with accessible label "Wishlist, 3 items".

## 10.9 Wishlist drawer (Pro)

Slide-over panel (right, ~400px) on desktop / bottom sheet on mobile, opened from the counter (setting: counter opens page [default] or drawer). Content: last ~10 items (image, name, price, remove), footer: "View wishlist" + "Add all to cart". Purpose: glanceable recall without leaving the current page. **Pro, not MVP** — it's a delight multiplier, not a core job; the counter + fast wishlist page cover MVP recall.

## 10.10 Blocks, shortcodes, Elementor

- **Blocks (MVP):** `Add to Wishlist Button` (attrs: product context/auto, style variant, show label), `Wishlist` (the page experience; attrs: layout list/grid, items per page), `Wishlist Counter` (attrs: show count, link target page/drawer[Pro]). Block settings mirror the global settings but scoped; server-rendered dynamic blocks so state stays cache-safe.
- **Shortcodes (MVP):** `[flexa_wishlist_button product_id=""]`, `[flexa_wishlist]`, `[flexa_wishlist_counter]` — same attributes as blocks.
- **Elementor (MVP):** three widgets wrapping the same renderers; Content controls = block attrs; Style controls = limited (colors inherit preset; spacing/alignment only). One rendering pipeline for all three surfaces — blocks/shortcodes/widgets are thin wrappers (binding architectural constraint).

## 10.11 Feedback vocabulary (canonical strings)

Add: "Added to {list} — View · Change[Pro]" · Remove: "Removed — Undo" · Cart: "Added to cart — View cart" · Copy: "Link copied" · Merge: "Your saved items were added to your wishlist." · Generic failure: "Something went wrong. Please try again." All toasts: bottom-center mobile / bottom-left desktop, max 1 visible (queue replaces), 3.5s, pause on hover/focus, `aria-live="polite"`, never modal. All strings translatable with pluralization where counts appear.

---

# 11. Admin UX Specification

Built with the Flexa admin design system (flexa-plugin-ui): React 18 + Vite + Tailwind v4 (`fw:` prefix) + Radix/shadcn-vendored primitives + TanStack Query + Zustand + Sonner-style Toaster, hash router, fixed 56px header with logo + nav (Dashboard / Wishlists / Analytics / Settings), `SettingRow` + `TabCard` composition, WP-admin CSS override block, `manage_options` for settings routes.

## 11.1 Dashboard (Free: basic · Pro: full)

Top KPI row (4 stat cards): **Total wishlists** (with active-in-30-days subline) · **Items saved (30d)** · **Wishlist → cart rate (30d)** (Pro; Free shows locked teaser) · **Wishlist → purchase rate (30d)** (Pro). Below, two panels:
- **Most wishlisted products** (top 10; product, count, trend arrow, stock badge) — the one report every merchant wants. Free: top 5, no trend.
- **Needs attention** (Pro): actionable list — "Wishlisted but out of stock" (restock candidates) and "Wishlisted > 30 days, never carted" (discount candidates). Each row links to the product edit screen. This is the "actionable insights" mandate: every number implies a next step.
Empty/new-install state: friendly onboarding card ("Your wishlist is live — here's where insights will appear") + link to view the wishlist page + settings.

## 11.2 Analytics (Pro)

One page, three sections (no tab maze): **Trend** (line: adds/removes per day, 30/90d toggle) · **Funnel** (saved → added to cart → purchased, with rates; attribution = cart additions originating from wishlist actions, purchase = order containing that product by that user within the configured attribution window — approximation stated in UI copy) · **Products table** (sortable: saves, conversions, current stock; CSV export [ADV]). No realtime, no per-visitor tracking, no external calls (§18).

**Attribution window (setting):** inline select in the Analytics page header — options **7 / 14 / 30 / 60 / 90 days / Custom** (custom = numeric days input), default **30 days**; stored in the settings schema (e.g. `analytics.attributionWindowDays`). Helper text beneath the control: "Determines how long a purchase can be attributed to a wishlist interaction." Changing it affects future attribution display; historical aggregates are not recomputed (stated in the helper tooltip).

## 11.3 Wishlists browser (Pro)

Table: owner (user or "Guest"), list name, items, visibility, created, last activity. Filters: search by user email/product, visibility, date range. Row → detail drawer: item list (read-only), share status. **v1 is read-only** — merchants inspect, not edit, customer data (support/debug + trust; editing customers' lists is a liability). Free shows the page with aggregate counts + upgrade prompt.

## 11.4 Settings

Tabs (sidebar on desktop, stacked on mobile — flexa-extra pattern). Control budget: ~28 controls total. Every row = icon + label + one-line description + right-aligned control.

- **General:** Enable wishlist (master) · Wishlist page (page picker; auto-created at install) · Default wishlist name label · Guest wishlists (on) · Guest retention days (30) · After login merge notice (on) · "Remove item after adding to cart" (off) · "After add to cart" (stay [default] / go to cart).
- **Appearance:** Preset (Flexa / Minimal / Classic / Custom — §11.5) · Accent color (default: **inherit theme/Woo primary**) · Icon style (heart / heart-outline / bookmark / star) · Corner radius (S/M/L) · Button placement on product page (after add-to-cart / before / after summary / manual) · Loop button (overlay / under title / off) · Wishlist page layout (list / grid) · Counter auto-inject (**off**).
- **Wishlist Page:** Show stock status (on) · Show date added (on) · Show "Add all to cart" (on) · Items per page (24) · Sort default [ADV].
- **Sharing:** Enable sharing (on) · Channels multi-select (Copy link + native always on; Email/WhatsApp/X/Facebook/Pinterest toggles) · Social image (media picker; falls back to first product image) · Social title/description templates.
- **Notifications (Pro):** Price-drop emails (off) · Drop threshold % (5) · Back-in-stock (off) · Quantity UI (off) · Email sender name/address (defaults to store) · Consent text override.
- **Advanced:** Custom CSS · Delete data on uninstall (off) · Danger zone: "Reset all wishlist data" (typed-confirm dialog → shared Resetter).

Save model: single right-aligned Save button in the page header, disabled until dirty, partial-payload save (changed fields only), success/failed state inline + toast — exactly the flexa-plugin-ui settings recipe.

## 11.5 Appearance presets

### Decision — presets + accent color instead of per-element styling
**Why:** the merchant job is "make it match my store," not "design a button." One accent color + radius + icon choice covers ~95% of stores; incumbents' 40 color pickers are the anti-pattern we're selling against. **Alternatives:** full style editor (settings overload), zero options (agencies need an escape hatch). **Recommendation:** 4 presets — **Flexa** (default: soft-filled pill button, filled heart, subtle shadow), **Minimal** (ghost/outline, hairline), **Classic** (rectangular, theme-button-like, matches trad themes), **Custom** (unlocks accent/radius/icon rows; plus the Advanced custom CSS box). The accent color defaults to **inheriting the theme's/WooCommerce's primary color** (best-effort detection with a safe neutral fallback), so the button matches the store out of the box; merchants override with one setting. Presets are token bundles over CSS custom properties (`--fw-*`) so themes/agencies can override cleanly; storefront inherits theme font always.

---

# 12. UI Component System

Storefront components are framework-light (vanilla/behavior-attached, no React on the storefront — §17); admin components follow flexa-plugin-ui. Names below are the canonical component inventory; each entry: purpose · conceptual inputs · key states (full matrix §13) · a11y notes.

| Component | Purpose | Conceptual inputs | Notes (interaction / a11y / responsive) |
|---|---|---|---|
| `WishlistButton` | Toggle save state anywhere | product, variation?, variant (labelled/icon), placement ctx | §10.2; `aria-pressed`, dynamic accessible name, 44px target |
| `WishlistCounter` | Global recall entry | count, target (page/drawer) | §10.8; no "0" badge; `aria-label` with count |
| `WishlistPage` | Owner list experience | wishlist, layout, page | §10.4; composes header, items, pagination, empty state |
| `WishlistItemCard` | One saved item | item (product, variation, price, stock, snapshot), capabilities | list & grid renditions; degraded "ghost" mode (§9.14) |
| `WishlistAddToCart` | Item purchase action | item, quantity? | states: default/loading/added/select-options/out-of-stock/external |
| `WishlistQuantity` (Pro) | Desired qty stepper | item, min/max/step | debounced persist; inputmode numeric; labelled group |
| `WishlistSelector` (Pro) | Choose target list | lists, currentId, pendingItem? | popover/bottom-sheet duality (§10.3); listbox semantics |
| `WishlistSwitcher` (Pro) | Change viewed list | lists, currentId | chips/dropdown (§10.3) |
| `WishlistCreateDialog` (Pro) | New list | name, visibility | §9.4; focus trap, initial focus on name |
| `WishlistShare` | Share entry + surface | list, channels, shareUrl | §20; popover (desktop)/native sheet (mobile) |
| `WishlistVisibilityControl` (Pro) | Private/Shared/Public | list | radio popover, §9.11; one-line explanations |
| `WishlistPriceChange` (Pro) | Price-drop badge | snapshot, current | "↓ $30" pill + old price strikethrough; text not color-only |
| `WishlistStockStatus` | Stock line | product/variation stock | in-stock silent; low/OOS/backorder shown; pairs with NotifyMe |
| `WishlistNotifyMe` (Pro) | Back-in-stock subscribe | product/variation, email?, consent | §9.13 states; guest email validation inline |
| `WishlistDrawer` (Pro) | Quick glance panel | recent items | §10.9; `role=dialog`, focus trap, Esc, swipe-down dismiss |
| `WishlistEmptyState` | Zero-item view | ctx (owner/shared), recentlyViewed? | §10.7 |
| `WishlistNotice` (toast) | All feedback | message, actions[], tone | §10.11; singleton, polite live region |
| `WishlistMoreMenu` | Item overflow actions | item, capabilities | menu semantics; bottom sheet on mobile |

Admin-side reuses the design-system primitives (Button, Input, Switch, Select, Dialog, Tooltip, SettingRow, TabCard, StatCard, DataTable, EmptyState, DangerZone, Toaster) — no new admin primitives are invented for this plugin.

---

# 13. State Matrix

Critical interactive components; cells = visual + behavior. All focus states = visible ring (admin: brand ring; storefront: 2px accent outline w/ offset), never suppressed.

| Component | Default | Hover | Focus | Loading | Success | Error | Disabled | Empty |
|---|---|---|---|---|---|---|---|---|
| **WishlistButton** | ♡ outline + label "Add to wishlist" (`aria-pressed=false`) | bg tint, icon scale 1.05 | ring + hover style | in-flight: keeps optimistic state, dup-clicks queued | ♥ filled, label "In wishlist" (`aria-pressed=true`), pop anim | reverts, toast w/ Retry | not used (unavailable products hide the button) | — |
| **WishlistCounter** | heart + badge n | underline/tint per theme | ring | badge skeleton until hydrate | badge updates w/ subtle pulse | hidden badge, icon still links | — | icon only, no badge |
| **WishlistSelector** | list of lists, current checked | row bg | roving focus, arrows | row spinner on choose | check moves, closes, toast | inline row error, stays open | rows never disabled | "No lists yet" + New list |
| **WishlistItemCard** | full card | action reveal (desktop), always-visible actions (touch) | card outline on focus-within | skeleton card on page load | — | ghost mode (§9.14) | — | — |
| **WishlistAddToCart** | "Add to cart" | darken | ring | spinner ≥300ms, label "Adding…" | "Added ✓" 2s → default; toast | store error toast; state refresh | OOS: replaced by stock status; parent-variable: "Select options" link-style | — |
| **WishlistQuantity** | − n + | button tint | ring per control | tick "saving" after debounce | subtle check | revert + toast | at min/max the respective button disables | — |
| **WishlistShare** | "Share" button | tint | ring | link-generation spinner (first share) | "Link copied ✓" inline 2s | clipboard fallback field | sharing disabled by merchant → button absent | — |
| **WishlistNotifyMe** | "Notify me when available" | tint | ring | "Subscribing…" | "We'll email you ✓" + Unsubscribe | inline email/consent error | already purchasable → absent | — |
| **WishlistDrawer** | panel open, items | row hover | trap + ring | skeleton rows | — | inline retry | — | mini empty state + CTA |
| **Admin Save (settings)** | disabled "Save" | — | ring | "Saving…" | "Saved ✓" 2s | "Retry" + error toast | enabled only when dirty | — |

Mobile column (applies to all): touch targets ≥44px, hover states replaced by active/pressed feedback, overflow actions move into bottom sheets.

---

# 14. Responsive Behaviour

Breakpoints (storefront, content-driven): base (mobile, ≤640) / md (tablet, 641–1024) / lg (desktop, >1024).

| Surface | Mobile | Tablet | Desktop |
|---|---|---|---|
| Wishlist page | single-column card list; header actions collapse: Share + "⋯"; Add-all becomes a **sticky bottom bar** when list ≥3 purchasable items; grid alt = 2-col | card list wider; grid 3-col | card list with roomy metadata; grid 4-col; header actions inline |
| WishlistButton (page) | full-width-friendly under add-to-cart, min 44px height | inline | inline |
| WishlistButton (card) | always visible (no hover-reveal — touch) | visible | overlay may fade-in on hover if preset "Minimal", else visible |
| Selector / Share / MoreMenu | bottom sheets w/ drag handle, swipe-dismiss | popovers | popovers |
| Drawer (Pro) | bottom sheet, 70vh | right slide-over 360px | right slide-over 400px |
| Counter | inherits theme header/mobile nav; block placeable in mobile menu | — | — |
| Admin SPA | stacked tabs, single-column rows, tables → stacked defn-lists | sidebar tabs appear | full layout per flexa-plugin-ui |

Rules: no horizontal scroll ever required for core actions; long product titles clamp to 2 lines with title attr/full name for AT; RTL fully mirrored (icons with directional meaning flip; heart does not); text sizes respect user font scaling (rem-based); layouts tolerate +40% string length (German/Finnish) without truncating controls.

---

# 15. Accessibility (WCAG 2.2 AA — binding requirements)

1. **Keyboard:** every action reachable and operable by keyboard; visible focus always; logical order; popovers/sheets/dialogs trap focus, close on Esc, return focus to invoker.
2. **Toggle semantics:** WishlistButton is a `button` with `aria-pressed` + accessible name including the product ("Add {product} to wishlist"); name updates on toggle.
3. **Live feedback:** toasts in a polite live region; state changes additionally announced through the control's own name/state change (no toast-only communication of state).
4. **Never color alone:** state = icon fill + label/text change; price drop = arrow glyph + text; stock = text label.
5. **Contrast:** ≥4.5:1 text, ≥3:1 UI components against adjacent colors — presets are pre-validated; the accent-color picker warns (non-blocking) when a chosen accent fails contrast on white.
6. **Touch targets:** ≥44×44 CSS px for all interactive storefront elements.
7. **Dialogs/sheets:** `role="dialog"`, labelled by their heading; background inert.
8. **Menus/listboxes:** WishlistSelector uses listbox semantics; MoreMenu uses menu semantics with arrow-key navigation.
9. **Images:** product images in wishlist = product title alt; decorative icons `aria-hidden`.
10. **Reduced motion:** all non-essential animation (pop, pulses, sheet spring) disabled under `prefers-reduced-motion`; sheets still transition instantly.
11. **Forms:** notify-me email field labelled, errors programmatically associated (`aria-describedby`), not placeholder-only.
12. **Admin app:** inherits flexa-plugin-ui a11y rules (focus-visible ring, labelled controls, portal theming).

Acceptance: axe-core clean on wishlist page, product page w/ button, share popover, selector sheet; full keyboard walkthrough of Flows 1–7 and 10.

---

# 16. WooCommerce Compatibility

## 16.1 Product type matrix

| Type | Button | Wishlist item behavior |
|---|---|---|
| Simple | normal | add-to-cart inline |
| Variable (parent) | card: saves parent; page: saves selected variation if chosen & valid, else parent | parent item → "Select options" → product page; variation item → direct add-to-cart with attribute summary shown |
| Variation | — | stores variation id + human-readable attribute summary snapshot |
| Grouped | saves the grouped (parent) product | "View products" link (no direct cart add) |
| External/affiliate | normal | "Buy at {store}" outbound link, external icon, `rel=noopener` |
| Virtual / Downloadable | normal | normal |
| Subscription (plugin) | normal | add-to-cart delegates to the subscription plugin's own handler; price line shows the product's own price HTML (never recomputed) |
| Bundle/Composite (plugins) | saves the bundle parent | "Select options"-style: configuration happens on the product page |
| Backorder | normal | "Available on backorder" text + normal add-to-cart |
| Out of stock | button still available (saving OOS items is a core job) | stock line + disabled cart + NotifyMe (Pro) |
| Hidden / Private / Draft / Trashed / Deleted | button hidden on inaccessible contexts | ghost card (§9.14); shared-view hides ghosts entirely from visitors |

## 16.2 Variation rules (exhaustive — the "developer must know exactly" list)

1. Product page with valid full selection → save (product, variation, attribute summary).
2. Partial/no selection → save parent only; no nagging to select.
3. Same product, different variations = distinct wishlist items; parent + a variation of it may coexist.
4. Toggle-state on the product page reflects the *current selection*: heart shows filled when the exact selected variation (or the parent, when nothing selected) is saved; changing selection re-evaluates the state.
5. Variation price change → wishlist shows live price; snapshot untouched (feeds price-drop logic per item as saved).
6. Variation OOS → item's stock line + NotifyMe target the variation.
7. Variation deleted/disabled → §9.14 fallback.
8. All variation display uses the attribute summary snapshot for labels, re-validated against live data at render.

## 16.3 Platform integration points (conceptual)

Declare HPOS compatibility; respect catalog visibility and price display filters (tax/currency plugins see normal Woo price rendering paths — the plugin never formats prices itself, it renders WooCommerce-provided price HTML); multi-currency: snapshots store amount+currency and comparisons only occur in matching currency (else badge suppressed); purchase attribution reads orders via Woo CRUD only; WPML/Polylang: wishlist stores canonical product ids, renders translated titles at view time, one wishlist across languages.

---

# 17. Performance Architecture

Budgets (binding): storefront JS ≤ 15KB gz (one file, zero dependencies, no React); CSS ≤ 8KB gz; **zero blocking requests** added to any page; ≤1 wishlist-related request on normal page views (the batched state call, and none when local cache is fresh); wishlist page initial render ≤ 2 added queries beyond item fetch (batch product loads).

Mechanisms and their reasons:

1. **Cache-safe hydration (reason: page caches are the norm).** Cacheable markup is user-agnostic; one `GET /state` returns `{count, itemKeys[], listSummaries[]}` for the current owner; response mirrored to localStorage with a short TTL (~60s) so repeat navigations hydrate with zero requests; mutation responses piggyback fresh state.
2. **Conditional assets (reason: most pages need at most the button).** Frontend script/style enqueue only where a wishlist surface renders; wishlist-page-specific logic loads only there; admin app only on the plugin's admin screen.
3. **Optimistic UI + single-flight mutations (reason: perceived speed is the feature).** Toggle applies instantly; requests are queued last-write-wins per item; failures roll back (§13).
4. **Server-side pagination (24/page) + batched product hydration (reason: 1,000-item lists must not melt).** Item queries paginated and indexed by (wishlist, position/date); product data loaded via batch lookups, never per-item queries.
5. **Counts denormalized/cached (reason: counter on every page).** Item counts maintained on write (or cached per owner), invalidated on mutation — never a COUNT(*) per page view.
6. **Async intelligence (reason: never tax the request path).** Price scan = daily batched job over *distinct wishlisted products* (not items); back-in-stock sends via queued batches on stock-transition events; both use Action Scheduler-style queues, no external services.
7. **Guest hygiene (reason: unbounded guest rows).** Guest wishlists expire after the retention window (default 30 days rolling) via a daily cleanup job; token refresh on interaction extends life.
8. **Analytics writes are O(1) event counters** (daily aggregate rows), not per-visitor logs.

Explicitly rejected: WebSockets/live sync (no job requires it), storefront framework runtime (budget), infinite scroll (footer reachability), preloading wishlist data on every page (the counter needs a number, not items).

---

# 18. Privacy / GDPR

1. **Guest lifecycle (§18.1):** guest identity = random signed token in a first-party cookie (SameSite=Lax; not readable as PII). Stored guest data: token hash, items, timestamps — no IP, no fingerprint. Expiry: retention setting (default 30d rolling); expired data hard-deleted by the cleanup job. The cookie is functional (consented-by-action when the user saves an item); document it for cookie banners; no cookie is set before the first wishlist interaction.
2. **Account data:** wishlists/items are personal data tied to the user id. **Exporter**: registers a WordPress personal-data exporter group "Wishlists" (lists, items, dates, notification subscriptions). **Eraser**: registers an eraser that deletes all wishlists, items, share slugs, and notification subscriptions for the email/user. Both cover back-in-stock subscriptions stored by email (guests).
3. **Uninstall:** "Delete data on uninstall" setting (off by default); uninstall honors it; the Danger-zone reset is the same shared destructive path.
4. **Consent model (§18.4):** three distinct categories, never conflated — *functional* (wishlist itself, no consent UI needed beyond cookie disclosure), *notification* (price-drop / back-in-stock emails: explicit opt-in per §9.12/9.13, revocable via one-click unsubscribe + account setting), *marketing* (promotional campaigns: **not built** — non-goal; the CSV export of consented emails [ADV] requires the notification consent flag and is labeled accordingly).
5. **Sharing privacy (§18.5):** share slugs are long and unguessable; shared views show owner display name only (never email), setting to show "A customer's wishlist" instead; Private lists return a neutral "This wishlist isn't available" (no existence disclosure); visibility changes take effect immediately; "Reset link" hard-revokes. Shared/public views are `noindex` unless Public *and* the merchant enables indexing (default off).
6. **Data minimalism:** no behavioral tracking, no third-party calls, analytics are first-party aggregates only.

---

# 19. Notifications (Pro)

**Categories:** transactional-notification emails only (price drop, back in stock). No marketing sends from this plugin (§27).

1. **Infrastructure:** one queued email pipeline; templates follow WooCommerce email styling (inherit store header/footer/colors) so they look native; all copy translatable; every email has: product card(s) with image/name/old-new price or stock line, one CTA ("View your wishlist" / "Buy now"), unsubscribe link (per-category), store identity.
2. **Price drop (§9.12):** daily scan job diffs current price vs per-item snapshot for consented users; threshold setting (default 5%); per-user daily digest cap (never one email per item); snapshot updates to the notified price after send (no repeat alerts for the same drop); UI badge independent of email consent.
3. **Back in stock (§9.13):** subscription entity (email, product/variation, consent timestamp, status); triggered on OOS→in-stock transition; batch-queued sends; subscription completes after one send; wishlist UI surfaces subscribe/unsubscribe states; guest subscriptions allowed with explicit consent checkbox.
4. **Merchant controls:** per-category enable (both default off), sender identity, threshold, consent-text override — all in Settings → Notifications; a test-send button per template.
5. **Non-email surfaces:** in-UI badges (price drop, back-in-stock "it's back") always work without any email consent — the wishlist page itself is the primary notification surface.

---

# 20. Sharing & Visibility

1. **Model:** visibility ∈ {private (default), shared, public}. Free tier: private/shared on the single default list. Pro: per-list control incl. public. Share URL = `/wishlist/{slug}` style pretty permalink (rewrite-based, conceptually) with the unguessable slug; slug created on first share, regenerable ("Reset link").
2. **Share surface (§9.10):** mobile-first — Web Share API native sheet when available; fallback/desktop popover: **Copy link** primary (full-width), then icon row of enabled channels (Email, WhatsApp, X, Facebook, Pinterest — each a prefilled share-intent URL, `noopener`). No wall of persistent social buttons on the page itself; one Share button.
3. **Shared view (§10.4):** clean read-only page; visitor actions: per-item Add to cart, "Save all to my wishlist"; owner attribution per §18.5; OG/Twitter meta: social title template ("{name}'s wishlist at {store}"), description template, image (setting → fallback first item image).
4. **Revocation & errors:** Private/Reset-link per §9.11/§18.5; invalid/revoked slug → designed "not available" page with store CTA (no 404 leak of existence, no error page).
5. **MVP boundary:** Copy link + native share + channel row + OG meta = MVP (on the default list). QR code, public profile/discovery = Future.

---

# 21. REST / API Requirements (conceptual contract)

Namespace `flexa-wishlist/v1`; envelope + controller + permission conventions per Flexa lineage (BaseRestController, real `permission_callback` everywhere, per-arg sanitization, explicit statuses). Storefront identity = logged-in cookie auth or guest token; nonce'd. Rate-limit mutation endpoints modestly (abuse guard).

**Storefront (owner-scoped; "owner" = current user or guest token; guests are restricted to their own single list):**

| Capability | Sketch |
|---|---|
| Get state (hydration) | `GET /state` → count, item keys, list summaries |
| Get wishlist(s) | `GET /lists`, `GET /lists/{id}` (paginated items, batched product data) |
| Create / rename / delete list (Pro) | `POST /lists`, `PATCH /lists/{id}`, `DELETE /lists/{id}` (delete: move-items option) |
| Add item | `POST /lists/{id}/items` (product, variation?, qty?) — idempotent on dupes |
| Remove item | `DELETE /items/{id}` (+ restore for undo, short window) |
| Move/copy item (Pro) | `POST /items/{id}/move` (target list, copy flag) |
| Update quantity (Pro) | `PATCH /items/{id}` |
| Update visibility (Pro) | `PATCH /lists/{id}` (visibility) |
| Share link | `POST /lists/{id}/share` (create/regenerate slug) |
| Shared view (public) | `GET /shared/{slug}` — no auth; respects visibility |
| Save-all from shared | `POST /shared/{slug}/save` (into caller's default list) |
| Add-to-cart bridge | uses Woo Store API / existing cart endpoints — this plugin adds no cart endpoints |
| Notify-me (Pro) | `POST /stock-subscriptions`, `DELETE /stock-subscriptions/{id}` (guest: email+consent required) |

**Admin (capability-gated):** settings get/save (`manage_options`, partial-merge semantics), dashboard/analytics reads (`manage_woocommerce`), wishlist browser reads (Pro, `manage_woocommerce`), reset (`manage_options`, shared Resetter). All controllers registered in the single RegisterFacade; `flexa_wishlist/rest/register_routes` extension hook fires last.

**Permissions summary:** owners mutate only their own lists; shared/public reads require only the slug; visibility=private blocks non-owners including admins via REST (admins use the admin read endpoints, which are audit-scoped); every endpoint validates ownership server-side (ids are never trusted).

---

# 22. Event System

**PHP hooks (server truth, for integrations/automation):**

| Hook | Why it matters |
|---|---|
| `flexa_wishlist/item/added` (item, list, owner ctx) | marketing/CRM integrations, analytics counters |
| `flexa_wishlist/item/removed` | churn signal, counter maintenance |
| `flexa_wishlist/item/added_to_cart` | conversion attribution (the funnel's middle) |
| `flexa_wishlist/item/purchased` | funnel completion (fired on order-paid attribution match) |
| `flexa_wishlist/list/created` · `deleted` · `visibility_changed` | Pro lifecycle, audit |
| `flexa_wishlist/list/shared` | virality signal |
| `flexa_wishlist/guest/merged` (guest items, user) | CRM identity stitching |
| `flexa_wishlist/price/dropped` (product, old, new) | powers email job; lets merchants plug other channels |
| `flexa_wishlist/stock/restored_notified` | downstream automation |
| `flexa_wishlist/settings/updated` ($new,$old) | lineage convention |
| `flexa_wishlist/data_reset` | lineage convention (Resetter) |
| Filters: `flexa_wishlist/default_settings`, `flexa_wishlist/js_config`, `flexa_wishlist/capabilities/*`, `flexa_wishlist/pro/is_licensed`, `flexa_wishlist/item/data` (extend item payloads), `flexa_wishlist/share/channels` | extension seams per conventions |

**Storefront CustomEvents (for theme/tracking integration, GTM-friendly):** `fw:item-added`, `fw:item-removed`, `fw:item-added-to-cart`, `fw:list-shared`, `fw:drawer-opened` — dispatched on `document` with a detail payload (product id, variation id, list id, source surface). Reason: lets merchants wire GA4/pixel events without touching plugin code.

---

# 23. Developer Handoff

## 23.1 Feature specs

The flows (§9), component specs (§10–§13), and platform rules (§16–§22) together form the per-feature spec in the mandated shape (goal/user/preconditions/trigger/UI/behavior/states/persistence/guest/logged-in/errors/a11y/performance/acceptance). Cross-reference index:

| Feature | Spec sections | Acceptance |
|---|---|---|
| Wishlist button & toggle | §10.2, §13, §16.2 | §24 A1–A4 |
| Guest persistence & merge | §9.1, §9.8–9.9, §18.1 | §24 B1–B4 |
| Wishlist page | §10.4–10.7, §14 | §24 C1–C5 |
| Add to cart (single/all) | §9.6–9.7, §16.1 | §24 D1–D3 |
| Counter | §10.8 | §24 E1 |
| Sharing | §9.10–9.11, §20 | §24 F1–F3 |
| Multiple lists (Pro) | §9.3–9.5, §10.3 | §24 G1–G3 |
| Price tracking (Pro) | §9.12, §19.2 | §24 H1–H2 |
| Back-in-stock (Pro) | §9.13, §19.3 | §24 H3 |
| Admin | §11 | §24 I1–I3 |
| Platform (REST/events/GDPR/i18n/perf) | §15, §17, §18, §21, §22 | §24 J1–J5 |

## 23.2 Persistence requirements (conceptual, no SQL)

Custom tables via the lineage `Install\Migrator` + `Domain/*Repository` pattern:

- **Wishlists:** id, owner_user_id (nullable), guest_token_hash (nullable; exactly one of the two), name, is_default, visibility, share_slug (nullable, unique), created/updated. Indexed by owner, by slug.
- **Wishlist items:** id, wishlist_id, product_id, variation_id (0/none), attribute summary (denormalized text snapshot), quantity, price snapshot (amount, currency), position, date_added. Unique per (wishlist, product, variation). Indexed by wishlist, by product (for analytics/price scan).
- **Stock subscriptions (Pro):** id, email, user_id?, product_id, variation_id, status, consent timestamp, created/notified. Unique per (email, product, variation, active).
- **Analytics aggregates (Pro):** daily rows: date, product_id, adds, removes, carted, purchased. No raw event log.
- **Options:** single `flexa_wishlist_settings`. Counts cache per owner (transient/derived — implementation freedom).

## 23.3 Product decisions (final — do not change casually)

1. Button is an optimistic toggle; no modal in the primary add path (§9.2, §9.3).
2. Guest wishlists are server-persisted via signed cookie token; localStorage is a cache only, never the source of truth (§9.1).
3. Guest→account merge is silent, additive, deduped by product+variation, idempotent (§9.9).
4. Wishlist page default is the card list; no table layout exists (§10.4).
5. Add-to-cart keeps the item and stays on the wishlist by default (§9.6).
6. Free = complete single-wishlist product incl. link sharing; Pro = lists/visibility/intelligence/drawer/analytics (§28–§29).
7. Appearance = presets + accent + radius + icon + custom CSS; no per-element style farm (§11.5).
8. Storefront ships no framework runtime; budgets in §17 are hard.
9. Cache-safe hydration is the only rendering mode (§10.2, §17.1).
10. Notifications are opt-in, digest-capped, category-separated; no marketing sends (§19, §18.4).
11. Naming/architecture binding in §0 is final.
12. Distribution & licensing: Free tier ships on **WordPress.org** (must meet .org review guidelines: readme.txt, GPL, no crippled features, tasteful Pro teasers only); Pro is an **add-on sold on Flexacommerce** using the in-house Flexa licensing infrastructure (file-level Pro split per §0, gated by `flexa_wishlist/pro/is_licensed`). Free launches first; the Pro build follows.

## 23.4 Implementation freedom

Exact table/column names and index strategy (within §23.2 semantics); storefront script internals (event delegation, hydration scheduling); queue implementation (Action Scheduler vs WP-Cron batching); counts caching mechanism; admin chart library (within design system); rewrite vs query-var share routes; undo-restore mechanics; test frameworks; template-override mechanism (filterable renderers acceptable in MVP; document the chosen seam).

---

# 24. Acceptance Criteria (testable; Given/When/Then)

**A. Button**
- A1: Given any shopper on a product page, when they click Add to Wishlist, the control reflects the saved state in <100ms perceived (optimistic) without any page reload, and the state survives a hard refresh.
- A2: Given a saved product, when the shopper clicks the (now filled) button, the item is removed and a toast with Undo appears; Undo restores the item with its original date-added.
- A3: Given a full-page-cached shop page, when two different shoppers view it, each sees their own correct button states after hydration, and the cached HTML contains no user-specific wishlist markup.
- A4: Given a variable product with a valid selected variation, when saved, the wishlist stores that variation and displays its attribute summary; with no selection, the parent is stored and the wishlist item offers "Select options".
- A5: Given a failed add request, the button reverts to unsaved and an error toast with Retry appears; no phantom item exists server-side.

**B. Guest & merge**
- B1: Given a guest who saves 5 products and closes the browser, when they return within the retention window on the same browser, all 5 items and the correct counter appear.
- B2: Given a guest with items who logs into an account with an existing wishlist containing 2 of the same product+variation pairs, when login completes, the account list contains the union with no duplicates and no deletions, and exactly one confirmation toast is shown.
- B3: Given a completed merge, the guest cookie token no longer resolves to any wishlist (repeat login runs cause no duplicates).
- B4: Given a guest wishlist past the retention window with no interactions, the cleanup job removes it entirely.
- B5: No wishlist cookie exists before the first wishlist interaction.

**C. Wishlist page**
- C1: Given a wishlist with items, the page renders the card list with image, linked title, live price, stock (when not in stock), and actions, matching §10.4 on mobile and desktop with no horizontal scroll.
- C2: Given 100 items, the page shows 24 with Load more, and total added queries respect §17 budgets.
- C3: Given zero items, the designed empty state (§10.7) renders with a working shop CTA.
- C4: Given an item whose product was deleted, a ghost card renders with Remove as the only action; the page never errors.
- C5: Given a shared-view visitor, no owner-only actions render, and a Private list's URL shows the neutral unavailable screen.

**D. Cart**
- D1: Given a purchasable simple/variation item, Add to cart adds exactly that product/variation to the Woo cart, shows the success state, and the shopper remains on the wishlist with the item retained (defaults).
- D2: Given "Remove after adding" enabled, the item is removed only after a confirmed successful cart add.
- D3: Given Add all with a mix (2 purchasable, 1 parent-variable, 1 OOS), exactly 2 items are added and the summary toast reports the 2 skips with reasons.

**E. Counter**
- E1: Given any page with the counter, its badge equals the owner's total item count after hydration, shows no badge at zero, and links to the wishlist page; adding/removing anywhere updates it in-session.

**F. Sharing**
- F1: Given an owner who creates a share link, an unauthenticated visitor with that URL sees the read-only shared view; OG meta matches the templates.
- F2: Given visibility switched to Private (or link reset), the old URL immediately shows the unavailable screen.
- F3: Given a mobile browser with Web Share support, tapping Share opens the native sheet; on desktop the popover's Copy link places the URL on the clipboard and confirms inline.

**G. Multiple lists (Pro)**
- G1: Given a Pro user with 3 lists, tapping the heart adds to the default (or sticky last-used) list in one tap; the toast names the list and offers Change.
- G2: Given Change → another list, the just-added item ends up only in the chosen list, and both counts are correct.
- G3: Given deleting a non-empty list via "move items", all items appear in Favorites afterward; the default list itself cannot be deleted.

**H. Intelligence (Pro)**
- H1: Given an item saved at $129 whose price becomes $99, after the daily scan the item shows the drop badge with old/new/delta text (not color-only).
- H2: Given a consented user with 3 dropped items in one day, exactly one digest email is sent; without consent, no email but the badge still shows.
- H3: Given a subscribed OOS product that returns to stock, each active subscriber receives exactly one email and the subscription completes; unsubscribe links work without login.

**I. Admin**
- I1: Given activation with defaults untouched, the storefront passes the §60 quality-bar walkthrough (button, page, guest, feedback, empty state, mobile) with zero settings changes.
- I2: Given a settings change, Save is enabled only when dirty, persists a partial payload, and a reload reflects the saved values; an invalid value is sanitized/rejected without wiping other settings.
- I3: Given the danger-zone reset with typed confirmation, all wishlist data is removed via the shared reset path and the same result is achievable via WP-CLI.

**J. Platform**
- J1: axe-core reports no violations on the four surfaces in §15; Flows 1–7 and 10 complete keyboard-only.
- J2: Storefront asset budgets (§17) verified in CI (size check) and assets absent on pages without wishlist surfaces.
- J3: WordPress export/erase requests include/remove all wishlist and subscription data for the target email.
- J4: All user-facing strings translatable; UI intact with 40%-longer pseudo-locale and in RTL.
- J5: Every REST route rejects cross-owner access attempts (403) including guessed ids, and phpstan level 6 is clean.

---

# 25. Parallel Development Plan

Module boundaries with contracts; agents work independently against §0 naming, §21 API shapes, §22 events, §23.2 persistence semantics. Suggested order-of-integration: A → (B, C) → D → E/F.

| Agent | Scope | Owns | Contract surface (must not change unilaterally) |
|---|---|---|---|
| **A — Core domain** | entities, repositories, migrations, guest tokens, merge, counts, Settings schema, Resetter, capabilities, PHP events | `Domain/*`, `Install/*`, `Support/*` | event signatures (§22), repository semantics (§23.2), settings schema keys (§11.4) |
| **B — Storefront** | button/counter/page/empty/toasts/hydration script, shortcodes, cart bridge | frontend assets, renderers | consumes REST (§21) + emits `fw:` events; DOM/class prefix `fw-`; presets' CSS custom properties |
| **C — Admin app** | React SPA: dashboard, settings, (Pro: wishlists browser, analytics UI) | `apps/admin/` | consumes admin REST; flexa-plugin-ui system; no new primitives |
| **D — Sharing/visibility** | slugs, shared view, share surface, OG meta, revocation | share renderer + routes | visibility semantics (§20), shared-view read contract |
| **E — Notifications (Pro)** | price scan, stock subscriptions, email pipeline/templates, consent | jobs + email layer | listens to A's events; owns subscription entity; consent flags (§18.4) |
| **F — Integrations** | blocks, Elementor, REST controller wiring, WPML config, exporter/eraser, WP-CLI | thin wrappers | wraps B's renderers 1:1 (§10.10 single-pipeline rule) |

Shared foundation (Agent A ships first): plugin bootstrap, Settings, RegisterFacade, activation/migration. REST controllers are owned by the agent owning the domain (A: lists/items/state; D: share; E: subscriptions; C-adjacent: admin reads) but all register through the single facade.

---

# 26. Open Questions — all resolved (product owner, 2026-08-21)

No open questions remain. The answers below are final and have been folded into the body of this spec:

1. **Pricing/packaging → resolved:** Pro ships as an **add-on sold on Flexacommerce**, using the in-house Flexa licensing infrastructure (not Freemius). Recorded in §23.3.12.
2. **Free distribution → resolved:** Free tier is listed on **WordPress.org**; the Pro build follows later. The Free codebase must satisfy .org review constraints (readme.txt, GPL, no external calls, honest Pro teasers). Recorded in §23.3.12.
3. **Counter auto-injection → resolved: default OFF.** Auto-inject remains available as an opt-in setting; block/shortcode/Elementor are the primary placement paths. Updated in §10.8, §11.4, §28.
4. **"Save all to my wishlist" on shared views → resolved: stays in Free** (acquisition loop). Unchanged in §10.4/§20.
5. **Analytics attribution window → resolved: a setting**, options 7 / 14 / 30 / 60 / 90 days / Custom, default 30 days, with helper text "Determines how long a purchase can be attributed to a wishlist interaction." Specified in §11.2.
6. **Brand accent default → resolved: inherit the theme/Woo primary color** (revised decision, superseding an earlier Flexa-brand-color choice); merchants override with one setting. Specified in §11.4/§11.5.

---

# 27. Non-Goals (explicitly not built)

1. Traditional table layout for the wishlist page.
2. Promotional/marketing email campaign builder (integrate via hooks/CSV instead).
3. "Ask for an estimate" / quote workflows (future consideration, not designed).
4. Public wishlist search/browse directory.
5. Drag-and-drop manual reordering (sort options cover it).
6. Per-element style editor (colors for every button/table/cell).
7. Real-time sync across devices/tabs beyond normal hydration.
8. Wishlist item notes, registries with purchase-claiming ("mark as bought"), contributor-editable shared lists.
9. Storefront framework runtime (React/Vue on the storefront).
10. Any third-party/external service calls (analytics, sharing SDKs, CDNs).

*Confirmed by product owner (2026-08-21): do not build any of the above.*

---

# 28. Recommended MVP (Free v1.0 — exact launch scope)

Everything marked MVP in §8, concretely: one-tap toggle button (product page + loop, cache-safe), auto-created Favorites list, guest wishlists + silent merge, variation capture, wishlist page (card list + grid, empty state, pagination, ghost handling), per-item + add-all to cart, remove with undo, counter (block/shortcode/Elementor; auto-inject opt-in, off by default), link + native + channel sharing with OG meta on the default list, blocks/shortcodes/Elementor (3 surfaces each), price snapshots (data only), settings (General/Appearance/Wishlist Page/Sharing/Advanced with presets), basic dashboard (counts + top 5), GDPR exporter/eraser + retention + uninstall cleanup, i18n/RTL, REST + hooks + `fw:` events, WCAG AA, performance budgets. **Definition of done = §24 A–F + I1–I3 + J all green.** This Free product must be independently reviewable as "the best free wishlist available" — that is the funnel.

# 29. Recommended Pro Scope (v1.0 Pro)

Multiple wishlists (create/rename/delete/switch/default, selector + sticky target, move/copy), per-list visibility incl. Public, price-drop badges + consented digest emails, back-in-stock notifications, wishlist drawer, quantity (off-by-default), admin wishlists browser (read-only), analytics (trend/funnel/products + attention list). Business logic: Free wins install decisions with quality; Pro converts on the two motions merchants pay for — **more organization for shoppers** (lists/sharing/drawer) and **more revenue from intent** (price/stock intelligence + analytics). Every Pro feature is visible-but-locked in the admin (teaser cards), never crippled in the storefront.

# 30. Future Roadmap (post-1.0, in rough order)

1. Sort controls + CSV exports (ADV items graduating).
2. QR share + share-analytics (visits per share link).
3. Template-override system + theme developer docs.
4. Registry mechanics (mark-as-purchased on shared lists) — unlocks gift-list market properly.
5. Estimate/quote workflow (B2B) if demand appears.
6. Multi-currency-aware price tracking; price-history sparkline.
7. Headless/Store-API-first surface for block themes & external frontends.
8. Integration recipes: Klaviyo/Mailchimp/Brevo event forwarding (built on §22 hooks).
9. Item notes, list images/covers for shared lists.
10. Public discovery only if merchants demonstrably ask (privacy posture stays default-private).

---

## Final quality-bar walkthrough (§65 of the brief — verified against this spec)

Shopper: product → visible heart → one tap → instant fill + toast → keeps shopping → counter → wishlist page → variation shown → add to cart works → remove has undo. ✔ (§10.2, §10.4, §9.6)
Guest: 5 saves → browser closed → returns → intact → logs in → silent lossless merge. ✔ (§9.1, §9.9)
Power user: creates lists → sticky-target adds → moves items → shares one link → sees price-drop badge → gets stock email. ✔ (§9.3–9.5, §9.10, §9.12–9.13)
Merchant: installs → touches nothing → storefront looks professional → opens admin → 6 tabs, ~28 controls, presets → enables Pro → understands 3-block analytics immediately. ✔ (§11)

*End of specification.*
