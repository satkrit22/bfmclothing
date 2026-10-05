/* ==========================================================================
   BFM Clothing - global JavaScript (vanilla, no dependencies)

   AJAX endpoints (all under /api, all validate server-side; the browser is never trusted):
     POST api/cart.php          action=add|update|remove, variant_id, quantity   -> {ok, message, cart_count}
     POST api/wishlist.php      product_id                                       -> {ok, wishlisted, count, message}
     POST api/newsletter.php    email                                            -> {ok, message}
     GET  api/quickview.php     id                                               -> {ok, product:{...}}
     GET  api/search-suggest.php q                                               -> {ok, results:[{name,url,image,price_text}]}
   Every POST sends the CSRF token in the X-CSRF-Token header.
   ========================================================================== */
(function () {
  'use strict';

  var BFM = window.BFM || { base: '', csrf: '', loggedIn: false, currency: 'Rs.' };

  /* ---------------- tiny helpers ---------------- */
  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
  function h(tag, attrs, children) {
    var el = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'class') el.className = attrs[k];
      else if (k === 'text') el.textContent = attrs[k];
      else if (attrs[k] !== false && attrs[k] != null) el.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return el;
  }
  function svgIcon(name, cls) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('class', 'icon ' + (cls || ''));
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS(ns, 'use');
    use.setAttribute('href', '#i-' + name);
    svg.appendChild(use);
    return svg;
  }
  function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

  function api(path, opts) {
    opts = opts || {};
    var method = opts.method || 'POST';
    var url = BFM.base + '/' + path;
    var init = { method: method, headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': BFM.csrf }, credentials: 'same-origin' };
    if (opts.signal) init.signal = opts.signal;
    if (method === 'POST') init.body = new URLSearchParams(opts.data || {});
    else if (opts.data) url += '?' + new URLSearchParams(opts.data).toString();
    return fetch(url, init).then(function (res) {
      return res.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; })
        .then(function (json) { json._status = res.status; return json; });
    }).catch(function (err) {
      if (err && err.name === 'AbortError') throw err;
      return { ok: false, message: 'Network problem. Please check your connection and try again.', _status: 0 };
    });
  }

  function setLoading(btn, on) { if (btn) { btn.classList.toggle('is-loading', on); btn.disabled = on; } }

  /* ---------------- toasts ---------------- */
  function toast(message, type) {
    var region = $('#toastRegion');
    if (!region || !message) return;
    type = type || 'info';
    var icon = type === 'success' ? 'check' : (type === 'error' ? 'alert' : 'alert');
    var node = h('div', { class: 'toast toast-' + type, role: type === 'error' ? 'alert' : 'status' }, [
      svgIcon(icon), h('span', { class: 'toast-text', text: message })
    ]);
    region.appendChild(node);
    setTimeout(function () {
      node.classList.add('is-leaving');
      setTimeout(function () { node.remove(); }, 300);
    }, type === 'error' ? 6000 : 3500);
  }
  window.BFMToast = toast;

  (function showFlash() {
    var el = $('#bfm-flash');
    if (!el) return;
    try {
      JSON.parse(el.textContent || '[]').forEach(function (f) {
        toast(f[1], f[0] === 'danger' ? 'error' : f[0]);
      });
    } catch (e) { /* ignore */ }
  })();

  /* ---------------- sticky header ---------------- */
  (function () {
    var header = $('#siteHeader');
    if (!header) return;
    function onScroll() { header.classList.toggle('is-stuck', window.scrollY > 8); }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  })();

  /* ---------------- drawers (mobile menu, filters) ---------------- */
  var overlay = $('[data-overlay]');
  var openDrawerEl = null;
  var lastFocus = null;

  function openDrawer(el) {
    if (!el) return;
    lastFocus = document.activeElement;
    openDrawerEl = el;
    el.classList.add('is-open');
    el.setAttribute('aria-hidden', 'false');
    if (overlay) { overlay.hidden = false; requestAnimationFrame(function () { overlay.classList.add('is-open'); }); }
    document.body.classList.add('no-scroll');
    var first = $('a, button, input', el);
    if (first) setTimeout(function () { first.focus(); }, 60);
    $$('[data-nav-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
  }
  function closeDrawer() {
    if (!openDrawerEl) return;
    openDrawerEl.classList.remove('is-open');
    openDrawerEl.setAttribute('aria-hidden', 'true');
    openDrawerEl = null;
    if (overlay) { overlay.classList.remove('is-open'); setTimeout(function () { overlay.hidden = true; }, 250); }
    document.body.classList.remove('no-scroll');
    $$('[data-nav-open]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* ---------------- search overlay ---------------- */
  var searchPanel = $('#searchPanel');
  var searchInput = $('#searchInput');
  var suggestBox = $('#searchSuggest');
  var suggestCtrl = null;

  function openSearch() {
    if (!searchPanel) return;
    searchPanel.hidden = false;
    requestAnimationFrame(function () { searchPanel.classList.add('is-open'); });
    searchPanel.setAttribute('aria-hidden', 'false');
    if (overlay) { overlay.hidden = false; requestAnimationFrame(function () { overlay.classList.add('is-open'); }); }
    setTimeout(function () { if (searchInput) searchInput.focus(); }, 80);
  }
  function closeSearch() {
    if (!searchPanel || !searchPanel.classList.contains('is-open')) return;
    searchPanel.classList.remove('is-open');
    searchPanel.setAttribute('aria-hidden', 'true');
    if (overlay && !openDrawerEl) { overlay.classList.remove('is-open'); setTimeout(function () { overlay.hidden = true; }, 250); }
    setTimeout(function () { searchPanel.hidden = true; }, 300);
  }
  function renderSuggestions(results) {
    suggestBox.textContent = '';
    results.forEach(function (r) {
      var a = h('a', { href: r.url }, [
        h('img', { src: r.image, alt: '', width: '44', height: '55', loading: 'lazy' }),
        h('span', {}, [h('span', { class: 's-name', text: r.name }), h('br'), h('span', { class: 's-price', text: r.price_text })])
      ]);
      suggestBox.appendChild(h('li', {}, [a]));
    });
  }
  if (searchInput && suggestBox) {
    searchInput.addEventListener('input', debounce(function () {
      var q = searchInput.value.trim();
      if (q.length < 2) { suggestBox.textContent = ''; return; }
      if (suggestCtrl) suggestCtrl.abort();
      suggestCtrl = new AbortController();
      api('api/search-suggest.php', { method: 'GET', data: { q: q }, signal: suggestCtrl.signal })
        .then(function (res) { if (res.ok) renderSuggestions(res.results || []); })
        .catch(function () { /* aborted */ });
    }, 250));
  }

  /* ---------------- confirm dialog ---------------- */
  var confirmModal = $('#confirmModal');
  function confirmDialog(message, title, yesLabel) {
    return new Promise(function (resolve) {
      if (!confirmModal || typeof confirmModal.showModal !== 'function') { resolve(window.confirm(message)); return; }
      $('#confirmMessage').textContent = message;
      $('#confirmTitle').textContent = title || 'Are you sure?';
      $('[data-confirm-yes]', confirmModal).textContent = yesLabel || 'Confirm';
      function done(v) {
        confirmModal.close();
        confirmModal.removeEventListener('click', onClick);
        confirmModal.removeEventListener('cancel', onCancel);
        resolve(v);
      }
      function onClick(e) {
        if (e.target.closest('[data-confirm-yes]')) done(true);
        else if (e.target.closest('[data-confirm-no]') || e.target === confirmModal) done(false);
      }
      function onCancel(e) { e.preventDefault(); done(false); }
      confirmModal.addEventListener('click', onClick);
      confirmModal.addEventListener('cancel', onCancel);
      confirmModal.showModal();
    });
  }
  window.BFMConfirm = confirmDialog;

  /* ---------------- form validation ---------------- */
  function fieldError(input, message) {
    var wrap = input.closest('.form-group') || input.parentElement;
    var err = $('.field-error', wrap);
    if (!message) { if (err) err.remove(); input.classList.remove('is-invalid'); input.removeAttribute('aria-invalid'); return; }
    if (!err) {
      err = h('p', { class: 'field-error', role: 'alert' });
      (input.closest('.password-wrap') || input).insertAdjacentElement('afterend', err);
    }
    err.textContent = message;
    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid', 'true');
  }
  function validateForm(form) {
    var ok = true, firstBad = null;
    $$('input, select, textarea', form).forEach(function (input) {
      if (input.type === 'hidden' || input.disabled) return;
      var msg = '';
      if (input.dataset.match) {
        var other = $(input.dataset.match, form);
        if (other && input.value !== other.value) msg = 'The passwords do not match.';
      }
      if (!msg && !input.checkValidity()) {
        msg = input.validity.valueMissing ? 'This field is required.' :
              input.validity.typeMismatch && input.type === 'email' ? 'Enter a valid email address.' :
              input.validity.tooShort ? 'Use at least ' + input.minLength + ' characters.' :
              input.validationMessage;
      }
      fieldError(input, msg);
      if (msg) { ok = false; if (!firstBad) firstBad = input; }
    });
    if (firstBad) firstBad.focus();
    return ok;
  }
  document.addEventListener('input', function (e) {
    var t = e.target;
    if (t.classList && t.classList.contains('is-invalid')) fieldError(t, '');
  });

  /* ---------------- product variant picker (product page + quick view) ---------------- */
  function initVariantForm(form) {
    var variants;
    try { variants = JSON.parse(form.getAttribute('data-variants') || '[]'); } catch (e) { variants = []; }
    var hidden = $('[name="variant_id"]', form);
    var status = $('[data-stock-status]', form);
    var qtyInput = $('[name="quantity"]', form);
    var addBtns = $$('[data-add-to-cart], [data-buy-now]', form);

    function selected(name) { var r = $('input[name="' + name + '"]:checked', form); return r ? r.value : ''; }
    function find(size, color) {
      for (var i = 0; i < variants.length; i++) if (variants[i].size === size && variants[i].color === color) return variants[i];
      return null;
    }
    function update() {
      var size = selected('size'), color = selected('color');
      // grey-out sizes that are sold out in the chosen colour
      $$('input[name="size"]', form).forEach(function (r) {
        var v = color ? find(r.value, color) : null;
        r.disabled = color ? (!v || v.stock <= 0) : false;
        if (r.disabled && r.checked) r.checked = false;
      });
      size = selected('size');
      var v = size && color ? find(size, color) : null;
      if (hidden) hidden.value = v ? v.id : '';
      if (status) {
        status.className = 'stock-status';
        if (!size || !color) { status.textContent = 'Select a size and colour'; }
        else if (!v || v.stock <= 0) { status.textContent = 'Out of stock'; status.classList.add('stock-out'); }
        else if (v.stock <= 5) { status.textContent = 'Only ' + v.stock + ' left'; status.classList.add('stock-low'); }
        else { status.textContent = 'In stock'; status.classList.add('stock-in'); }
      }
      if (qtyInput) {
        qtyInput.max = v ? Math.min(v.stock, 10) : 10;
        if (+qtyInput.value > +qtyInput.max && +qtyInput.max > 0) qtyInput.value = qtyInput.max;
      }
      addBtns.forEach(function (b) { b.disabled = !!(size && color && (!v || v.stock <= 0)); });
    }
    form.addEventListener('change', update);
    update();

    function addToCart(btn, thenCheckout) {
      if (!hidden || !hidden.value) { toast('Please select a size and colour.', 'error'); return; }
      setLoading(btn, true);
      api('api/cart.php', { data: { action: 'add', variant_id: hidden.value, quantity: qtyInput ? qtyInput.value : 1 } })
        .then(function (res) {
          setLoading(btn, false);
          if (!res.ok) { toast(res.message || 'Could not add to cart.', 'error'); return; }
          updateCartCount(res.cart_count);
          if (thenCheckout) { window.location.href = BFM.base + '/checkout.php'; return; }
          toast(res.message || 'Added to your cart.', 'success');
          var modal = btn.closest('dialog'); if (modal) modal.close();
        });
    }
    form.addEventListener('click', function (e) {
      var add = e.target.closest('[data-add-to-cart]'), buy = e.target.closest('[data-buy-now]');
      if (add) { e.preventDefault(); addToCart(add, false); }
      if (buy) { e.preventDefault(); addToCart(buy, true); }
    });
    form.addEventListener('submit', function (e) { e.preventDefault(); });
  }
  $$('form[data-variants]').forEach(initVariantForm);

  /* ---------------- quantity steppers ---------------- */
  document.addEventListener('click', function (e) {
    var step = e.target.closest('[data-qty-step]');
    if (!step) return;
    var input = $('input', step.closest('.qty'));
    var min = +input.min || 1, max = +input.max || 99;
    var next = Math.max(min, Math.min(max, (+input.value || min) + (+step.dataset.qtyStep)));
    if (next !== +input.value) { input.value = next; input.dispatchEvent(new Event('change', { bubbles: true })); }
  });

  /* ---------------- cart count + cart page actions ---------------- */
  function updateCartCount(n) {
    $$('[data-cart-count]').forEach(function (el) { el.textContent = n; el.hidden = !n; });
  }
  function refreshCartRoot() {
    var root = $('#cart-root');
    if (!root) return Promise.resolve();
    return fetch(window.location.href, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var fresh = $('#cart-root', doc);
      if (fresh) root.innerHTML = fresh.innerHTML;
      var cnt = $('[data-cart-count]', doc); if (cnt) updateCartCount(cnt.hidden ? 0 : +cnt.textContent);
    });
  }
  function cartUpdate(variantId, qty) {
    return api('api/cart.php', { data: { action: 'update', variant_id: variantId, quantity: qty } }).then(function (res) {
      if (!res.ok) toast(res.message || 'Could not update the cart.', 'error');
      return refreshCartRoot();
    });
  }
  document.addEventListener('change', debounce(function (e) {
    var input = e.target.closest ? e.target.closest('[data-cart-qty]') : null;
    if (input) cartUpdate(input.dataset.cartQty, input.value);
  }, 350));
  document.addEventListener('click', function (e) {
    var rm = e.target.closest('[data-cart-remove]');
    if (!rm) return;
    e.preventDefault();
    confirmDialog('Remove this item from your cart?', 'Remove item', 'Remove').then(function (yes) {
      if (!yes) return;
      api('api/cart.php', { data: { action: 'remove', variant_id: rm.dataset.cartRemove } }).then(function (res) {
        if (!res.ok) { toast(res.message || 'Could not remove the item.', 'error'); return; }
        toast('Item removed.', 'success');
        refreshCartRoot();
      });
    });
  });

  /* ---------------- wishlist ---------------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-wishlist]');
    if (!btn) return;
    e.preventDefault();
    var id = btn.dataset.wishlist;
    setLoading(btn, true);
    api('api/wishlist.php', { data: { product_id: id } }).then(function (res) {
      setLoading(btn, false);
      if (res._status === 401) {
        toast('Log in to save items to your wishlist.', 'info');
        setTimeout(function () { window.location.href = BFM.base + '/login.php?next=' + encodeURIComponent(location.pathname + location.search); }, 900);
        return;
      }
      if (!res.ok) { toast(res.message || 'Could not update your wishlist.', 'error'); return; }
      $$('[data-wishlist="' + id + '"]').forEach(function (b) {
        b.classList.toggle('is-active', res.wishlisted);
        b.setAttribute('aria-pressed', res.wishlisted ? 'true' : 'false');
        b.setAttribute('aria-label', res.wishlisted ? 'Remove from wishlist' : 'Add to wishlist');
        var ic = $('.icon', b); if (ic) ic.classList.toggle('fill', res.wishlisted);
      });
      $$('[data-wishlist-count]').forEach(function (el) { el.textContent = res.count; el.hidden = !res.count; });
      toast(res.message, 'success');
      var onPage = $('[data-wishlist-page]');
      if (onPage && !res.wishlisted) {
        var card = btn.closest('.product-card'); if (card) card.remove();
        if (!$('.product-card', onPage)) window.location.reload();
      }
    });
  });

  /* ---------------- quick view ---------------- */
  var qvModal = $('#quickViewModal');
  var qvBody = $('#quickViewBody');

  function qvSkeleton() {
    qvBody.textContent = '';
    qvBody.appendChild(h('div', { class: 'quick-grid' }, [
      h('div', { class: 'skeleton', style: 'aspect-ratio:4/5' }),
      h('div', {}, [h('div', { class: 'skeleton', style: 'height:34px;margin-bottom:14px' }), h('div', { class: 'skeleton', style: 'height:22px;width:40%;margin-bottom:24px' }),
        h('div', { class: 'skeleton', style: 'height:90px;margin-bottom:24px' }), h('div', { class: 'skeleton', style: 'height:48px' })])
    ]));
  }
  function qvRender(p) {
    var sizes = [], colors = [];
    p.variants.forEach(function (v) {
      if (sizes.indexOf(v.size) < 0) sizes.push(v.size);
      if (!colors.some(function (c) { return c.name === v.color; })) colors.push({ name: v.color, hex: v.hex || '#111111' });
    });
    var form = h('form', { 'data-variants': JSON.stringify(p.variants.map(function (v) { return { id: v.id, size: v.size, color: v.color, stock: v.stock }; })) });
    form.appendChild(h('input', { type: 'hidden', name: 'variant_id', value: '' }));

    var sizeGroup = h('div', { class: 'option-group' }, [h('div', { class: 'option-label', text: 'Size' }), h('div', { class: 'chip-list' }, sizes.map(function (s, i) {
      return h('label', { class: 'chip' }, [h('input', { type: 'radio', name: 'size', value: s, checked: sizes.length === 1 ? 'checked' : false }), h('span', { text: s })]);
    }))]);
    var colorGroup = h('div', { class: 'option-group' }, [h('div', { class: 'option-label', text: 'Colour' }), h('div', { class: 'chip-list' }, colors.map(function (c, i) {
      var sw = h('label', { class: 'swatch', title: c.name }, [h('input', { type: 'radio', name: 'color', value: c.name, 'aria-label': c.name, checked: i === 0 ? 'checked' : false }), h('span', { style: 'background:' + (/^#[0-9a-fA-F]{6}$/.test(c.hex) ? c.hex : '#111') })]);
      return sw;
    }))]);
    form.appendChild(colorGroup);
    form.appendChild(sizeGroup);
    form.appendChild(h('p', { class: 'stock-status', 'data-stock-status': '', text: '' }));

    var qty = h('div', { class: 'qty' }, [
      h('button', { type: 'button', 'data-qty-step': '-1', 'aria-label': 'Decrease quantity' }, [svgIcon('minus')]),
      h('input', { type: 'number', name: 'quantity', value: '1', min: '1', max: '10', 'aria-label': 'Quantity', inputmode: 'numeric' }),
      h('button', { type: 'button', 'data-qty-step': '1', 'aria-label': 'Increase quantity' }, [svgIcon('plus')])
    ]);
    form.appendChild(h('div', { class: 'buy-row' }, [qty, h('button', { type: 'button', class: 'btn btn-primary', 'data-add-to-cart': '' }, [svgIcon('bag'), 'Add to cart'])]));

    var priceNow = h('span', { class: 'price-now', text: p.price_text });
    var price = h('p', { class: 'product-price' }, [priceNow]);
    if (p.discount > 0) { price.appendChild(h('s', { class: 'price-was', text: p.was_text })); price.appendChild(h('span', { class: 'badge badge-sale', text: '-' + p.discount + '%' })); }

    var grid = h('div', { class: 'quick-grid' }, [
      h('img', { src: p.image, alt: p.name, width: '600', height: '750' }),
      h('div', {}, [
        h('h2', { class: 'modal-title', text: p.name }), price,
        h('p', { text: p.short_description || '' }), form,
        h('a', { class: 'link-arrow', href: p.url }, ['View full details', svgIcon('arrow')])
      ])
    ]);
    qvBody.textContent = '';
    qvBody.appendChild(grid);
    initVariantForm(form);
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-quickview]');
    if (!btn || !qvModal) return;
    e.preventDefault();
    qvSkeleton();
    if (typeof qvModal.showModal === 'function') qvModal.showModal();
    api('api/quickview.php', { method: 'GET', data: { id: btn.dataset.quickview } }).then(function (res) {
      if (!res.ok) { qvModal.close(); toast(res.message || 'Could not load this product.', 'error'); return; }
      qvRender(res.product);
    });
  });
  if (qvModal) {
    qvModal.addEventListener('click', function (e) {
      if (e.target === qvModal || e.target.closest('[data-modal-close]')) qvModal.close();
    });
  }

  /* ---------------- gallery + tabs ---------------- */
  document.addEventListener('click', function (e) {
    var thumb = e.target.closest('[data-gallery-thumb]');
    if (thumb) {
      var main = $('[data-gallery-main]');
      if (main) { main.src = thumb.dataset.src; main.alt = thumb.dataset.alt || ''; }
      $$('[data-gallery-thumb]').forEach(function (t) { t.classList.toggle('is-active', t === thumb); });
    }
    var tab = e.target.closest('[data-tab]');
    if (tab) {
      $$('[data-tab]').forEach(function (t) { t.classList.toggle('is-active', t === tab); t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
      $$('[data-tab-panel]').forEach(function (p) { p.hidden = p.dataset.tabPanel !== tab.dataset.tab; });
    }
    var pw = e.target.closest('[data-toggle-password]');
    if (pw) {
      var input = $('input', pw.closest('.password-wrap'));
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      pw.textContent = show ? 'Hide' : 'Show';
    }
  });

  /* ---------------- newsletter ---------------- */
  document.addEventListener('submit', function (e) {
    var form = e.target.closest ? e.target.closest('[data-newsletter]') : null;
    if (!form) return;
    e.preventDefault();
    var input = $('input[type="email"]', form), btn = $('button[type="submit"]', form);
    if (!input.value || !input.checkValidity()) { toast('Please enter a valid email address.', 'error'); input.focus(); return; }
    setLoading(btn, true);
    api('api/newsletter.php', { data: { email: input.value } }).then(function (res) {
      setLoading(btn, false);
      toast(res.message || (res.ok ? 'Thanks for subscribing!' : 'Could not subscribe.'), res.ok ? 'success' : 'error');
      if (res.ok) form.reset();
    });
  });

  /* ---------------- global click + keyboard routing ---------------- */
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-nav-open]')) openDrawer($('#mobileNav'));
    else if (e.target.closest('[data-filter-open]')) openDrawer($('#filters'));
    else if (e.target.closest('[data-drawer-close]') || e.target.closest('[data-overlay]')) { closeDrawer(); closeSearch(); }
    else if (e.target.closest('[data-search-open]')) openSearch();
    else if (e.target.closest('[data-search-close]')) closeSearch();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeDrawer(); closeSearch(); }
  });
  window.addEventListener('resize', debounce(function () { if (window.innerWidth >= 992 && openDrawerEl) closeDrawer(); }, 150));

  /* ---------------- forms: validation, confirm, double-submit guard ---------------- */
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-newsletter')) return;

    if (form.hasAttribute('data-validate') && !form.dataset.validated) {
      if (!validateForm(form)) { e.preventDefault(); return; }
    }
    var submitter = e.submitter;
    var msg = (submitter && submitter.dataset.confirm) || form.dataset.confirm;
    if (msg && !form.dataset.confirmed) {
      e.preventDefault();
      confirmDialog(msg, (submitter && submitter.dataset.confirmTitle) || form.dataset.confirmTitle, (submitter && submitter.dataset.confirmYes) || form.dataset.confirmYes).then(function (yes) {
        if (!yes) return;
        form.dataset.confirmed = '1';
        if (form.requestSubmit) form.requestSubmit(submitter || undefined); else form.submit();
      });
      return;
    }
    // prevent double submits
    var btn = submitter || $('button[type="submit"]', form);
    if (btn && !btn.hasAttribute('data-no-lock')) setTimeout(function () { btn.disabled = true; btn.classList.add('is-loading'); }, 0);
  }, true);

  // Re-enable buttons if the page is restored from bfcache
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) $$('button.is-loading').forEach(function (b) { b.disabled = false; b.classList.remove('is-loading'); });
  });
})();
