/**
 * Flexa Wishlist — storefront engine.
 *
 * Dependency-free (no framework runtime — §17). Responsibilities:
 *  - Cache-safe hydration: one GET /state per page (localStorage cached ~TTL),
 *    then reconcile neutral server markup to the shopper's real saved state.
 *  - Optimistic toggle with rollback + toast (undo / retry).
 *  - Variation-aware single-product button state.
 *  - Wishlist page: render cards, add-to-cart, remove w/ undo, load more.
 *  - Shared view: save-all.
 *  - GA4/GTM-friendly `fw:` CustomEvents on document.
 */
(function () {
	'use strict';

	var CFG = window.flexaWishlistFront || {};
	if (!CFG.restUrl) {
		return;
	}

	var STATE_KEY = 'flexa-wl:state';
	var savedKeys = {}; // "productId:variationId" -> true
	var count = 0;

	// ---- REST helper -------------------------------------------------------

	function api(path, method, body) {
		return fetch(CFG.restUrl + path, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': CFG.nonce
			},
			body: body ? JSON.stringify(body) : undefined
		}).then(function (res) {
			return res.json().then(function (json) {
				if (!res.ok || (json && json.success === false)) {
					var msg = (json && json.message) || CFG.i18n.error;
					throw new Error(msg);
				}
				return json.data || {};
			});
		});
	}

	// ---- State cache -------------------------------------------------------

	function readCache() {
		try {
			var raw = window.localStorage.getItem(STATE_KEY);
			if (!raw) return null;
			var parsed = JSON.parse(raw);
			var ttl = (CFG.stateTtl || 60) * 1000;
			if (Date.now() - parsed.t > ttl) return null;
			return parsed.d;
		} catch (e) {
			return null;
		}
	}

	function writeCache(state) {
		try {
			window.localStorage.setItem(STATE_KEY, JSON.stringify({ t: Date.now(), d: state }));
		} catch (e) {}
	}

	function applyState(state) {
		savedKeys = {};
		count = state.count || 0;
		(state.itemKeys || []).forEach(function (k) {
			savedKeys[k] = true;
		});
		reconcileButtons();
		updateCounters();
	}

	function hydrate() {
		var cached = readCache();
		if (cached) {
			applyState(cached);
			return;
		}
		api('state').then(function (state) {
			writeCache(state);
			applyState(state);
		}).catch(function () {});
	}

	// ---- Button reconciliation --------------------------------------------

	function keyOf(btn) {
		return btn.getAttribute('data-fw-product') + ':' + (btn.getAttribute('data-fw-variation') || '0');
	}

	function reconcileButtons() {
		each(document.querySelectorAll('[data-fw-toggle]'), function (btn) {
			setBtnState(btn, !!savedKeys[keyOf(btn)]);
		});
	}

	function setBtnState(btn, saved) {
		btn.classList.toggle('fw-btn--saved', saved);
		btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
		var label = btn.querySelector('.fw-btn__label');
		var text = saved ? CFG.labels.added : CFG.labels.add;
		if (label) label.textContent = text;
		btn.setAttribute('aria-label', text);
		btn.setAttribute('title', text);
	}

	function updateCounters() {
		each(document.querySelectorAll('[data-fw-count]'), function (badge) {
			badge.textContent = count;
			if (count > 0) {
				badge.removeAttribute('hidden');
			} else {
				badge.setAttribute('hidden', '');
			}
		});
	}

	// ---- Toggle ------------------------------------------------------------

	function onToggleClick(btn) {
		var wasSaved = btn.classList.contains('fw-btn--saved');
		var productId = parseInt(btn.getAttribute('data-fw-product'), 10);
		var variationId = parseInt(btn.getAttribute('data-fw-variation') || '0', 10);

		// Optimistic flip.
		setBtnState(btn, !wasSaved);
		btn.classList.add('fw-btn--busy');

		api('items/toggle', 'POST', {
			productId: productId,
			variationId: variationId
		}).then(function (data) {
			btn.classList.remove('fw-btn--busy');
			if (data.state) {
				writeCache(data.state);
				applyState(data.state);
			}
			if (data.saved) {
				emit('fw:item-added', { productId: productId, variationId: variationId });
				toast(CFG.i18n.saved, {
					label: CFG.i18n.viewList,
					href: CFG.wishlistUrl
				});
			} else {
				emit('fw:item-removed', { productId: productId, variationId: variationId });
				toast(CFG.i18n.removed);
			}
		}).catch(function (err) {
			// Rollback.
			btn.classList.remove('fw-btn--busy');
			setBtnState(btn, wasSaved);
			toast(err.message || CFG.i18n.error, {
				label: CFG.i18n.retry,
				onClick: function () { onToggleClick(btn); }
			});
		});
	}

	// ---- Variation tracking (single product) ------------------------------

	function bindVariations() {
		var forms = document.querySelectorAll('form.variations_form');
		each(forms, function (form) {
			var btn = findFormButton(form);
			if (!btn) return;
			jqOn(form, 'found_variation', function (e, variation) {
				if (variation && variation.variation_id) {
					btn.setAttribute('data-fw-variation', variation.variation_id);
					setBtnState(btn, !!savedKeys[keyOf(btn)]);
				}
			});
			jqOn(form, 'reset_data', function () {
				btn.setAttribute('data-fw-variation', '0');
				setBtnState(btn, !!savedKeys[keyOf(btn)]);
			});
		});
	}

	function findFormButton(form) {
		var wrap = form.closest('.product') || document;
		return wrap.querySelector('[data-fw-toggle]');
	}

	function jqOn(el, evt, cb) {
		if (window.jQuery) {
			window.jQuery(el).on(evt, cb);
		}
	}

	// ---- Wishlist page -----------------------------------------------------

	var page = { el: null, listId: 0, page: 1, hasMore: false };

	function initPage() {
		page.el = document.querySelector('[data-fw-page]');
		if (!page.el) return;

		api('state').then(function (state) {
			writeCache(state);
			applyState(state);
			var def = (state.lists || []).filter(function (l) { return l.isDefault; })[0] || (state.lists || [])[0];
			if (!def || !def.count) {
				showPageEmpty();
				return;
			}
			page.listId = def.id;
			loadPage(1, true);
		}).catch(showPageEmpty);
	}

	function loadPage(p, replace) {
		api('lists/' + page.listId + '?page=' + p).then(function (data) {
			hide(page.el.querySelector('[data-fw-page-loading]'));
			var list = page.el.querySelector('[data-fw-page-list]');
			if (replace) list.innerHTML = '';
			if (!data.items || !data.items.length) {
				if (replace) { showPageEmpty(); return; }
			}
			data.items.forEach(function (item) {
				list.appendChild(pageCard(item));
			});
			show(list);
			syncAddAll();
			page.page = data.pagination.page;
			page.hasMore = data.pagination.hasMore;
			var footer = page.el.querySelector('[data-fw-page-footer]');
			var more = page.el.querySelector('[data-fw-load-more]');
			show(footer);
			if (page.hasMore) { show(more); } else { hide(more); }
		}).catch(showPageEmpty);
	}

	function showPageEmpty() {
		hide(page.el.querySelector('[data-fw-page-loading]'));
		hide(page.el.querySelector('[data-fw-page-list]'));
		show(page.el.querySelector('[data-fw-page-empty]'));
	}

	function pageCard(item) {
		var li = document.createElement('li');
		li.className = 'fw-card';
		li.setAttribute('data-fw-item', item.id);

		if (item.ghost || !item.product) {
			li.classList.add('fw-card--ghost');
			li.innerHTML = '<div class="fw-card__body"><p class="fw-card__title">' + esc(item.name) +
				'</p></div><button type="button" class="fw-card__remove" data-fw-remove="' + item.id + '">&times;</button>';
			return li;
		}

		var p = item.product;
		var cart = '';
		if (CFG.cartEnabled) {
			if (p.purchasable && !p.needsSelection) {
				cart = '<button type="button" class="fw-card__cart" data-fw-add-cart="' + item.id +
					'" data-fw-product="' + p.id + '">' + '＋ ' + t('cart') + '</button>';
			} else if (p.needsSelection) {
				cart = '<a class="fw-card__cart fw-card__cart--select" href="' + esc(p.permalink) + '">' + t('select') + '</a>';
			} else if (!p.inStock) {
				cart = '<span class="fw-card__stock">' + t('oos') + '</span>';
			}
		}

		li.innerHTML =
			'<a class="fw-card__media" href="' + esc(p.permalink) + '"><img src="' + esc(p.image.src) +
			'" alt="' + esc(p.image.alt) + '" loading="lazy"></a>' +
			'<div class="fw-card__body">' +
			'<a class="fw-card__title" href="' + esc(p.permalink) + '">' + esc(p.name) + '</a>' +
			(item.attributes ? '<div class="fw-card__attrs">' + esc(item.attributes) + '</div>' : '') +
			'<div class="fw-card__price">' + (p.priceHtml || '') + '</div>' +
			'<div class="fw-card__actions">' + cart +
			'<button type="button" class="fw-card__remove" data-fw-remove="' + item.id + '" aria-label="' + t('remove') + '">&times;</button>' +
			'</div></div>';
		return li;
	}

	function removeItem(id, cardEl) {
		api('items/' + id, 'DELETE').then(function (data) {
			if (cardEl && cardEl.parentNode) cardEl.parentNode.removeChild(cardEl);
			if (data.state) { writeCache(data.state); applyState(data.state); }
			emit('fw:item-removed', { itemId: id });
			toast(CFG.i18n.removed, {
				label: CFG.i18n.undo,
				onClick: function () {
					api('items/restore', 'POST', data.restore).then(function (r) {
						if (r.state) { writeCache(r.state); applyState(r.state); }
						if (page.el) loadPage(1, true);
					});
				}
			});
			if (page.el && !document.querySelector('[data-fw-item]')) showPageEmpty();
		}).catch(function (err) { toast(err.message || CFG.i18n.error); });
	}

	// ---- Cart bridge -------------------------------------------------------

	// Refresh WooCommerce's mini-cart / fragments after a server-side cart change.
	function refreshCartFragments() {
		if (window.jQuery) {
			window.jQuery(document.body).trigger('wc_fragment_refresh');
		}
	}

	// Show the "Add all" button only when the list has at least one cartable item.
	function syncAddAll() {
		var toolbar = page.el && page.el.querySelector('[data-fw-page-toolbar]');
		if (!toolbar || !CFG.cartEnabled) return;
		show(toolbar);
		var addAll = page.el.querySelector('[data-fw-add-all]');
		if (page.el.querySelector('[data-fw-add-cart]')) { show(addAll); } else { hide(addAll); }
	}

	function addToCart(itemId, btn) {
		if (btn) btn.classList.add('fw-btn--busy');
		api('cart/add', 'POST', { itemId: itemId }).then(function (data) {
			if (btn) btn.classList.remove('fw-btn--busy');
			refreshCartFragments();
			emit('fw:added-to-cart', { itemId: itemId, cartCount: data.cartCount });
			if (data.removed && data.state) { writeCache(data.state); applyState(data.state); }
			if (data.removed && page.el) { loadPage(1, true); }
			toast(CFG.i18n.addedCart, { label: CFG.i18n.viewCart, href: data.cartUrl });
		}).catch(function (err) {
			if (btn) btn.classList.remove('fw-btn--busy');
			toast(err.message || CFG.i18n.error);
		});
	}

	function addAllToCart(btn) {
		btn.classList.add('fw-btn--busy');
		btn.disabled = true;
		var original = btn.textContent;
		btn.textContent = CFG.i18n.addingAll;
		api('cart/add-all', 'POST', { listId: page.listId || 0 }).then(function (data) {
			btn.classList.remove('fw-btn--busy');
			btn.disabled = false;
			btn.textContent = original;
			refreshCartFragments();
			emit('fw:added-all', { added: data.added, skipped: (data.skipped || []).length, cartCount: data.cartCount });
			if (data.state) { writeCache(data.state); applyState(data.state); }
			if (page.el) { loadPage(1, true); }
			var skipped = data.skipped || [];
			if (skipped.length && data.added) {
				toast(skipped[0].message || CFG.i18n.someSkipped, { label: CFG.i18n.viewCart, href: data.cartUrl });
			} else if (skipped.length) {
				toast(skipped[0].message || CFG.i18n.someSkipped);
			} else {
				toast(CFG.i18n.addedCart, { label: CFG.i18n.viewCart, href: data.cartUrl });
			}
		}).catch(function (err) {
			btn.classList.remove('fw-btn--busy');
			btn.disabled = false;
			btn.textContent = original;
			toast(err.message || CFG.i18n.error);
		});
	}

	// ---- Shared view -------------------------------------------------------

	function initShared() {
		var btn = document.querySelector('[data-fw-save-all]');
		if (!btn) return;
		btn.addEventListener('click', function () {
			var slug = btn.getAttribute('data-fw-slug');
			btn.classList.add('fw-btn--busy');
			api('shared/' + slug + '/save', 'POST', {}).then(function (data) {
				btn.classList.remove('fw-btn--busy');
				if (data.state) { writeCache(data.state); applyState(data.state); }
				emit('fw:list-saved', { slug: slug, added: data.added });
				toast(data.added ? CFG.i18n.saved : CFG.i18n.removed);
			}).catch(function (err) {
				btn.classList.remove('fw-btn--busy');
				toast(err.message || CFG.i18n.error);
			});
		});
	}

	// ---- Toast -------------------------------------------------------------

	var toastEl;
	function toast(message, action) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'fw-toast';
			toastEl.setAttribute('role', 'status');
			toastEl.setAttribute('aria-live', 'polite');
			document.body.appendChild(toastEl);
		}
		toastEl.innerHTML = '';
		var span = document.createElement('span');
		span.className = 'fw-toast__msg';
		span.textContent = message;
		toastEl.appendChild(span);

		if (action && (action.label)) {
			var a = document.createElement(action.href ? 'a' : 'button');
			a.className = 'fw-toast__action';
			a.textContent = action.label;
			if (action.href) {
				a.href = action.href;
			} else {
				a.type = 'button';
				a.addEventListener('click', function () {
					hideToast();
					if (action.onClick) action.onClick();
				});
			}
			toastEl.appendChild(a);
		}

		toastEl.classList.add('fw-toast--show');
		clearTimeout(toastEl._t);
		toastEl._t = setTimeout(hideToast, 4000);
	}
	function hideToast() {
		if (toastEl) toastEl.classList.remove('fw-toast--show');
	}

	// ---- Utilities ---------------------------------------------------------

	function each(nodes, fn) { Array.prototype.forEach.call(nodes, fn); }
	function show(el) { if (el) el.removeAttribute('hidden'); }
	function hide(el) { if (el) el.setAttribute('hidden', ''); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function t(k) {
		var map = {
			cart: 'Add to cart', select: 'Select options', remove: 'Remove',
			oos: 'Out of stock'
		};
		return (CFG.i18n && CFG.i18n[k]) || map[k] || k;
	}
	function emit(name, detail) {
		if (!CFG.emitEvents) return;
		document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
	}

	// ---- Global click delegation ------------------------------------------

	document.addEventListener('click', function (e) {
		var toggle = e.target.closest && e.target.closest('[data-fw-toggle]');
		if (toggle) { e.preventDefault(); onToggleClick(toggle); return; }

		var remove = e.target.closest && e.target.closest('[data-fw-remove]');
		if (remove) {
			e.preventDefault();
			removeItem(parseInt(remove.getAttribute('data-fw-remove'), 10), remove.closest('[data-fw-item]'));
			return;
		}

		var addCart = e.target.closest && e.target.closest('[data-fw-add-cart]');
		if (addCart) {
			e.preventDefault();
			addToCart(parseInt(addCart.getAttribute('data-fw-add-cart'), 10), addCart);
			return;
		}

		var addAll = e.target.closest && e.target.closest('[data-fw-add-all]');
		if (addAll) { e.preventDefault(); addAllToCart(addAll); return; }

		var more = e.target.closest && e.target.closest('[data-fw-load-more]');
		if (more) { e.preventDefault(); loadPage(page.page + 1, false); return; }
	});

	// ---- Boot --------------------------------------------------------------

	function boot() {
		hydrate();
		bindVariations();
		initPage();
		initShared();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
