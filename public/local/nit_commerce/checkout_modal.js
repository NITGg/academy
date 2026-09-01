/*
 * NIT shared checkout modal (courses + subscriptions).
 *
 * A page mints its config + strings, then calls NitCheckout.open({...}) from a Buy button. The modal
 * previews the price (auto offer + optional coupon) via /local/nit_commerce/api.php?function=preview_discount
 * and, on Proceed, calls the caller's proceed(couponCode, methodId) to start the real checkout.
 *
 * Pass `methods` to have the buyer choose how to pay here, in the same sheet as
 * the price and the coupon, which is where the mobile app asks. Two or more are
 * needed for the strip to appear: one method is not a choice. proceed() then
 * receives the chosen id, or 0 when nothing was offered and the gateway should
 * pick for itself.
 *
 * Usage (from a server page):
 *   window.NIT_CO = { wwwroot: M.cfg.wwwroot, sesskey: M.cfg.sesskey,
 *                     commerce: '/local/nit_commerce/api.php', str: {...} };
 *   NitCheckout.open({ itemType:'course', itemId:10, name:'...', subtitle:'',
 *                      methods: [{id:2, name:'Visa', logo:'…', is_reference:false}],
 *                      proceed: function(code, methodId){ location = checkoutUrl + '&coupon_code=' + code; } });
 */
(function (w) {
  'use strict';

  var cfg = null, modal = null, els = {}, current = null;

  function S(k) { return (cfg && cfg.str && cfg.str[k] != null) ? cfg.str[k] : k; }
  function money(n) { return (Math.round(Number(n) * 100) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  // The currency of the item being bought (per-item, country-resolved). Falls back to the
  // configured co_currency only when the caller did not pass one.
  function cur() { return (current && current.currency) ? current.currency : S('co_currency'); }

  function el(tag, style, text) {
    var e = document.createElement(tag);
    if (style) { e.style.cssText = style; }
    if (text != null) { e.textContent = text; }
    return e;
  }

  // Every colour below is a Brand Colors role from the theme gallery
  // (theme/nit/gallery.php -> --nit-brand-*), so the modal recolours with the
  // saved palette and follows a category's group (see group() below). The old
  // --nit-dark* / --nit-accentgolddark / --nit-accentteal tokens came from the
  // retired "Colours" palette, which the gallery no longer edits -- that is why
  // the proceed button drifted off-brand. Hex fallbacks are the Group 1 seeds,
  // used only if the theme CSS is missing.
  var C = {
    bg: 'var(--nit-brand-background, #0c141f)',
    surface: 'var(--nit-brand-surface, #121e2d)',
    ink: 'var(--nit-brand-textprimary, #eef3f9)',
    muted: 'var(--nit-brand-textsecondary, #94a3b8)',
    // Split accent: `accent` is the NON-TEXT accent (card border, crown emblem,
    // the apply-button border) -> Accent role. `accenttext` is the TEXT accent
    // (the final price, the "apply" label) -> Accent Text role.
    accent: 'var(--nit-brand-accent, #5488c4)',
    accenttext: 'var(--nit-brand-accenttext, #7fabdb)',
    // The main call to action: same fill + label as the site's .btn-primary
    // (Brand Colors: Primary = "background main button", Text primary = "text
    // in buttons"), so Proceed matches every other primary button on the site.
    primary: 'var(--nit-brand-primary, #5488c4)',
    primaryhover: 'var(--nit-brand-primary-hover, #497ab0)',
    onprimary: 'var(--nit-brand-textprimary, #eef3f9)',
    // Money saved (offer / discount) is a positive state -> Success role.
    good: 'var(--nit-brand-success, #3fa877)',
    error: 'var(--nit-brand-error, #d07f43)',
    line: 'var(--nit-brand-borderprimary, #223244)'
  };

  // The modal is appended to <body>, i.e. OUTSIDE any .nit-brand-2 / .nit-brand-3
  // wrapper, so --nit-brand-* would always resolve to Group 1 even on a category
  // page skinned with another group. Mirror the page's switch class onto the
  // modal root so it re-resolves from the same group as the page behind it.
  function group(trigger) {
    if (!modal) { return; }
    var src = (trigger && trigger.closest) ? trigger.closest('.nit-brand-2, .nit-brand-3') : null;
    if (!src) { src = document.querySelector('.nit-brand-2, .nit-brand-3'); }
    modal.classList.remove('nit-brand-2', 'nit-brand-3');
    if (src) {
      modal.classList.add(src.classList.contains('nit-brand-3') ? 'nit-brand-3' : 'nit-brand-2');
    }
  }

  function build() {
    if (modal) { return; }
    modal = el('div', 'display:none; position:fixed; inset:0; background:rgba(3,8,20,.72); z-index:99999; align-items:center; justify-content:center; padding:16px;');

    var card = el('div', 'width:100%; max-width:460px; background:' + C.surface + '; border:1px solid color-mix(in srgb, ' + C.accent + ' 22%, transparent); border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,.5); overflow:hidden;');

    var head = el('div', 'padding:22px 24px 0;');
    var h3 = el('h3', 'display:flex; align-items:center; gap:10px; font-size:20px; font-weight:800; color:' + C.ink + '; margin:0;');
    h3.appendChild(el('span', 'color:' + C.accent + ';', '♛'));
    h3.appendChild(document.createTextNode(' ' + S('co_title')));
    head.appendChild(h3);
    head.appendChild(el('p', 'color:' + C.muted + '; font-size:14px; line-height:1.6; margin:10px 0 0;', S('co_intro')));
    card.appendChild(head);

    var box = el('div', 'margin:18px 24px; padding:16px; border:1px solid ' + C.line + '; border-radius:12px; background:' + C.bg + ';');
    els.name = el('div', 'font-size:15px; font-weight:700; color:' + C.ink + '; margin-bottom:6px;', '—');
    box.appendChild(els.name);
    els.subtitle = el('div', 'font-size:12px; color:' + C.muted + '; margin-bottom:12px; display:none;');
    box.appendChild(els.subtitle);

    box.appendChild(row(S('co_total'), (els.original = el('b', 'color:' + C.ink + ';', '—'))));
    els.offerRow = row(S('co_offer'), (els.offer = el('b', 'color:' + C.good + ';', '—')));
    els.offerRow.style.display = 'none';
    box.appendChild(els.offerRow);

    // Payment methods, as a horizontal strip of cards. Same position and shape
    // as the app's purchase sheet, so a student who has used one recognises the
    // other. Hidden entirely when the gateway offers no choice worth making.
    els.methodsWrap = el('div', 'margin:14px 0 4px; display:none;');
    els.methodsWrap.appendChild(el('div', 'font-size:13px; color:' + C.muted + '; margin-bottom:8px;', S('co_method')));
    els.methods = el('div', 'display:grid; grid-template-columns:repeat(auto-fit, minmax(104px, 1fr)); gap:8px;');
    els.methodsWrap.appendChild(els.methods);
    box.appendChild(els.methodsWrap);

    // Coupon input + apply.
    var cRow = el('div', 'display:flex; align-items:center; gap:8px; margin:12px 0;');
    cRow.appendChild(el('span', 'font-size:14px; color:' + C.muted + '; flex:0 0 auto;', S('co_coupon')));
    els.coupon = el('input', 'flex:1; min-width:0; background:' + C.surface + '; border:1px solid ' + C.line + '; border-radius:8px; color:' + C.ink + '; padding:7px 10px; font-size:14px;');
    els.coupon.type = 'text'; els.coupon.autocomplete = 'off';
    cRow.appendChild(els.coupon);
    els.apply = el('button', 'flex:0 0 auto; background:transparent; border:1px solid ' + C.accent + '; color:' + C.accenttext + '; border-radius:8px; padding:7px 14px; font-weight:700; cursor:pointer; font-size:14px;', S('co_apply'));
    els.apply.type = 'button';
    cRow.appendChild(els.apply);
    box.appendChild(cRow);

    els.couponErr = el('div', 'display:none; color:' + C.error + '; font-size:12px; margin:-6px 0 10px;', ' ');
    box.appendChild(els.couponErr);

    box.appendChild(row(S('co_discount'), (els.discount = el('b', 'color:' + C.good + ';', '0.00 ' + cur()))));

    var totalRow = el('div', 'border-top:1px solid ' + C.line + '; padding-top:12px; display:flex; justify-content:space-between; font-size:16px; font-weight:800;');
    totalRow.appendChild(el('span', 'color:' + C.ink + ';', S('co_total')));
    els.final = el('b', 'color:' + C.accenttext + ';', '—');
    totalRow.appendChild(els.final);
    box.appendChild(totalRow);
    card.appendChild(box);

    var secure = el('div', 'padding:0 24px; color:' + C.muted + '; font-size:13px; display:flex; align-items:center; gap:6px;');
    secure.appendChild(el('span', '', '🔒'));
    secure.appendChild(document.createTextNode(' ' + S('co_secure')));
    card.appendChild(secure);

    els.error = el('div', 'display:none; margin:12px 24px 0; color:' + C.error + '; font-size:13px;', ' ');
    card.appendChild(els.error);

    var actions = el('div', 'display:flex; justify-content:flex-end; gap:10px; padding:18px 24px 22px;');
    els.cancel = el('button', 'background:transparent; border:1px solid ' + C.line + '; color:' + C.muted + '; border-radius:8px; padding:9px 18px; font-weight:700; cursor:pointer;', S('co_cancel'));
    els.cancel.type = 'button';
    // Primary call to action: solid Primary fill + Text primary label, matching
    // the site's .btn-primary (theme/nit/scss/components/_buttons.scss).
    els.proceed = el('button', 'background:' + C.primary + '; border:0; color:' + C.onprimary + '; border-radius:8px; padding:9px 20px; font-weight:800; cursor:pointer;', S('co_proceed'));
    els.proceed.type = 'button';
    // Inline styles cannot carry :hover, so mirror the theme's primary hover token.
    els.proceed.addEventListener('mouseenter', function () { els.proceed.style.background = C.primaryhover; });
    els.proceed.addEventListener('mouseleave', function () { els.proceed.style.background = C.primary; });
    actions.appendChild(els.cancel);
    actions.appendChild(els.proceed);
    card.appendChild(actions);

    modal.appendChild(card);
    document.body.appendChild(modal);

    // Wiring.
    els.cancel.addEventListener('click', close);
    modal.addEventListener('click', function (ev) { if (ev.target === modal) { close(); } });
    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && modal.style.display !== 'none') { close(); } });
    els.apply.addEventListener('click', function () { preview(els.coupon.value.trim()); });
    els.proceed.addEventListener('click', function () {
      if (!current) { return; }
      els.proceed.disabled = true;
      try { current.proceed(els.coupon.value.trim(), els.methodid || 0); }
      catch (e) { els.proceed.disabled = false; els.error.textContent = String(e && e.message || e); els.error.style.display = ''; }
    });
  }

  // Draw the method cards and remember which one is picked. Returns nothing;
  // the choice lives in els.methodid until proceed() reads it.
  function methods(list) {
    els.methods.innerHTML = '';
    els.methodid = 0;

    // One method is not a choice: showing it would be a decision the buyer
    // cannot make differently. Let the gateway take it silently, as before.
    if (!list || list.length < 2) {
      els.methodsWrap.style.display = 'none';
      return;
    }

    var cards = [];
    function select(i) {
      els.methodid = Number(list[i].id) || 0;
      cards.forEach(function (c, n) {
        c.style.borderColor = (n === i) ? C.accent : C.line;
        c.style.background = (n === i) ? 'color-mix(in srgb, ' + C.accent + ' 12%, transparent)' : C.surface;
      });
    }

    list.forEach(function (m, i) {
      var card = el('button', 'background:' + C.surface + '; border:1px solid ' + C.line +
        '; border-radius:10px; padding:10px; cursor:pointer; text-align:center; color:' + C.ink + ';');
      card.type = 'button';

      if (m.logo) {
        var img = el('img', 'height:22px; max-width:100%; object-fit:contain; display:block; margin:0 auto 6px;');
        img.src = m.logo;
        img.alt = '';
        // A broken logo should cost the card its image, not its label.
        img.addEventListener('error', function () { img.style.display = 'none'; });
        card.appendChild(img);
      }

      card.appendChild(el('div', 'font-size:12px; line-height:1.35;', m.name || ''));
      if (m.is_reference) {
        card.appendChild(el('div', 'font-size:11px; color:' + C.muted + '; margin-top:2px;', S('co_method_code')));
      }

      card.addEventListener('click', function () { select(i); });
      cards.push(card);
      els.methods.appendChild(card);
    });

    select(0);
    els.methodsWrap.style.display = '';
  }

  function row(label, valueEl) {
    var r = el('div', 'display:flex; justify-content:space-between; font-size:14px; color:' + C.muted + '; margin-bottom:10px;');
    r.appendChild(el('span', '', label));
    r.appendChild(valueEl);
    return r;
  }

  function close() { if (modal) { modal.style.display = 'none'; } current = null; }

  // Fetch a fresh price preview (auto offer + optional coupon) and paint the modal.
  function preview(code) {
    if (!current) { return; }
    var url = cfg.wwwroot + cfg.commerce + '?function=preview_discount&item_type=' + encodeURIComponent(current.itemType) +
      '&item_id=' + encodeURIComponent(current.itemId) + '&coupon_code=' + encodeURIComponent(code || '') +
      '&sesskey=' + encodeURIComponent(cfg.sesskey);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res || res.status !== 'success') { throw new Error('preview failed'); }
        var d = res.data || {};
        els.original.textContent = money(d.original != null ? d.original : (current.price || 0)) + ' ' + cur();
        els.final.textContent = money(d.final != null ? d.final : (current.price || 0)) + ' ' + cur();
        els.discount.textContent = money(d.discount || 0) + ' ' + cur();
        // Offer line (auto-applied), with its name if present.
        var offerDisc = Number(d.offer_discount || 0);
        if (offerDisc > 0) {
          var oname = (d.offers && d.offers[0] && d.offers[0].name) ? d.offers[0].name : (d.offer_name || '');
          els.offer.textContent = '-' + money(offerDisc) + ' ' + cur() + (oname ? ('  (' + oname + ')') : '');
          els.offerRow.style.display = '';
        } else {
          els.offerRow.style.display = 'none';
        }
        if (d.coupon_error) { els.couponErr.textContent = d.coupon_error; els.couponErr.style.display = ''; }
        else { els.couponErr.style.display = 'none'; }
      })
      .catch(function () { els.couponErr.textContent = S('co_coupon_failed'); els.couponErr.style.display = ''; });
  }

  var NitCheckout = {
    init: function (config) { cfg = config; build(); },
    open: function (item) {
      if (!cfg) { return; }
      build();
      group(item.trigger);
      current = item;
      els.name.textContent = item.name || '—';
      if (item.subtitle) { els.subtitle.textContent = item.subtitle; els.subtitle.style.display = ''; }
      else { els.subtitle.style.display = 'none'; }
      els.coupon.value = '';
      els.couponErr.style.display = 'none';
      els.error.style.display = 'none';
      els.proceed.disabled = false;
      var base = money(item.price || 0) + ' ' + cur();
      els.original.textContent = base; els.final.textContent = base; els.discount.textContent = '0.00 ' + cur();
      els.offerRow.style.display = 'none';
      methods(item.methods);
      modal.style.display = 'flex';
      preview(''); // Auto-apply any offer + fetch the true base.
    },
    close: close
  };

  w.NitCheckout = NitCheckout;
})(window);
