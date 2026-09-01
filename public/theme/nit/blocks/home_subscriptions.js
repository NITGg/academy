    (function() {
      var root = (document.currentScript && document.currentScript.closest(
          '[data-nit-subs]')) ||
        document.querySelector('[data-nit-subs]');
      if (!root || root.getAttribute('data-nit-wired')) {
        return;
      }
      root.setAttribute('data-nit-wired', '1');
      var grid = root.querySelector('[data-nit-subs-grid]');
      var tpl = root.querySelector('[data-nit-subs-card]');
      var msg = root.querySelector('[data-nit-subs-msg]');
      var modal = root.querySelector('[data-nit-modal]');
      if (modal && modal.parentNode) {
        document.body.appendChild(modal);
      }
      var base = (window.M && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : (root
        .getAttribute('data-base') || '');
      var sesskey = (window.M && M.cfg && M.cfg.sesskey) ? M.cfg.sesskey : '';
      var subsUrl = base + root.getAttribute('data-endpoint');
      var commerceUrl = base + root.getAttribute('data-commerce');
      // Who is looking, according to the server. The body class alone is not enough: Moodle
      // only adds 'notloggedin' when NOBODY is signed in, and a guest IS signed in — so a
      // guest used to reach the checkout dialog instead of the login page. The plan feed
      // answers this (res.viewer) and overwrites both values below; until it lands, the
      // body class is the safe pessimistic guess.
      var loggedIn = !document.body.classList.contains('notloggedin');
      var loginUrl = base + '/login/index.php';
      var current = null;

      function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) {
          return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;'
          } [c];
        });
      }

      function money(n) {
        return (Math.round(Number(n) * 100) / 100).toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function moneyShort(n) {
        var v = Math.round(Number(n) * 100) / 100;
        return v % 1 === 0 ? String(v) : v.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function strip(html) {
        var d = document.createElement('div');
        d.innerHTML = html || '';
        return (d.textContent || '').trim();
      }

      function say(t) {
        if (msg) {
          msg.textContent = t;
          msg.style.display = '';
        }
      }

      function q(sel) {
        return modal ? modal.querySelector(sel) : null;
      }

      // Currency label of the plan currently in the modal (falls back to EGP).
      function curLabel() {
        return (current && current.currency) ? current.currency : 'EGP';
      }

      function setTxt(sel, val) {
        var el = q(sel);
        if (el) {
          el.textContent = val;
        }
      }

      function setDisp(sel, val) {
        var el = q(sel);
        if (el) {
          el.style.display = val;
        }
      }

      function getVal(sel) {
        var el = q(sel);
        return el ? String(el.value || '').trim() : '';
      }

      // ── Modal ──
      function previewSub(code) {
        if (!current) {
          return;
        }
        var url = commerceUrl +
          '?function=preview_discount&item_type=subscription&item_id=' +
          current.id +
          '&coupon_code=' + encodeURIComponent(code || '') + '&sesskey=' +
          encodeURIComponent(sesskey);
        fetch(url, {
            headers: {
              'Accept': 'application/json'
            }
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(res) {
            if (!res || res.status !== 'success') {
              throw new Error('preview failed');
            }
            var d = res.data || {};
            setTxt('[data-nit-m-original]', money(d.original != null ? d
              .original : current.price) + ' ' + curLabel());
            setTxt('[data-nit-m-discount]', money(d.discount || 0) +
              ' ' + curLabel());
            setTxt('[data-nit-m-final]', money(d.final != null ? d.final :
              current.price) + ' ' + curLabel());
            var offerDisc = Number(d.offer_discount || 0);
            if (offerDisc > 0) {
              var oname = (d.offers && d.offers[0] && d.offers[0].name) ? d
                .offers[0].name : (d.offer_name || '');
              setTxt('[data-nit-m-offer]', '-' + money(offerDisc) + ' ' + curLabel() +
                (oname ? ('  (' + oname + ')') : ''));
              setDisp('[data-nit-m-offerrow]', 'flex');
            } else {
              setDisp('[data-nit-m-offerrow]', 'none');
            }
            if (d.coupon_error) {
              setTxt('[data-nit-m-couponerr]', d.coupon_error);
              setDisp('[data-nit-m-couponerr]', '');
            } else {
              setDisp('[data-nit-m-couponerr]', 'none');
            }
          })
          .catch(function() {
            setTxt('[data-nit-m-couponerr]',
              '{mlang en}Could not apply coupon.{mlang}{mlang ar}تعذّر تطبيق الكوبون.{mlang}'
            );
            setDisp('[data-nit-m-couponerr]', '');
          });
      }

      // Which method the visitor picked, and the list the gateway offers. 0
      // means the dialog never asked, and the gateway then chooses for itself
      // exactly as it used to.
      var methodId = 0;
      var methodList = null;

      // One method is not a choice, so the strip stays hidden and the charge
      // goes straight through — same rule as the course checkout and the app.
      function paintMethods(methods) {
        var wrap = q('[data-nit-m-methods]');
        var list = q('[data-nit-m-methodslist]');
        methodId = 0;
        if (!wrap || !list) {
          return;
        }
        if (!methods || methods.length < 2) {
          wrap.style.display = 'none';
          return;
        }

        list.innerHTML = '';
        var cards = [];
        var select = function(i) {
          methodId = Number(methods[i].id) || 0;
          cards.forEach(function(c, n) {
            var on = (n === i);
            c.style.borderColor = on ? 'var(--nit-brand-accent)' :
              'color-mix(in srgb, var(--nit-brand-textprimary) 15%, transparent)';
            c.style.background = on ?
              'color-mix(in srgb, var(--nit-brand-accent) 12%, transparent)' :
              'var(--nit-brand-surface)';
            c.setAttribute('aria-pressed', on ? 'true' : 'false');
          });
        };

        methods.forEach(function(m, i) {
          var card = document.createElement('button');
          card.type = 'button';
          card.style.cssText = 'flex: 0 0 auto; min-width: 132px; border-radius: 10px;' +
            ' border: 1px solid color-mix(in srgb, var(--nit-brand-textprimary) 15%, transparent);' +
            ' background: var(--nit-brand-surface); color: var(--nit-brand-textprimary);' +
            ' padding: 10px; cursor: pointer; text-align: center; font-size: 12px;' +
            ' line-height: 1.35;';

          if (m.logo) {
            var img = document.createElement('img');
            img.src = m.logo;
            img.alt = '';
            img.style.cssText = 'height: 22px; max-width: 100%; object-fit: contain;' +
              ' display: block; margin: 0 auto 6px;';
            // A broken logo costs the card its picture, not its label.
            img.addEventListener('error', function() {
              img.style.display = 'none';
            });
            card.appendChild(img);
          }

          var name = document.createElement('div');
          name.textContent = m.name || '';
          card.appendChild(name);

          card.addEventListener('click', function() {
            select(i);
          });
          cards.push(card);
          list.appendChild(card);
        });

        select(0);
        wrap.style.display = '';
      }

      // Fetched once and reused: the server caches the gateway's answer for an
      // hour, and the list does not change between two plans on one page.
      function loadMethods(currency) {
        if (methodList) {
          paintMethods(methodList);
          return;
        }
        fetch(subsUrl + '?function=get_payment_methods&currency=' +
            encodeURIComponent(currency || 'EGP') + '&sesskey=' +
            encodeURIComponent(sesskey), {
            headers: {
              'Accept': 'application/json'
            }
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(res) {
            methodList = (res && res.status === 'success' && res.data) ? res.data : [];
            paintMethods(methodList);
          })
          .catch(function() {
            // No list means no question, which is where this started.
            paintMethods([]);
          });
      }

      function openModal(sub) {
        current = sub;
        setTxt('[data-nit-m-name]', sub.name);
        setTxt('[data-nit-m-days]', sub.days);
        setTxt('[data-nit-m-original]', money(sub.price) + ' ' + curLabel());
        setTxt('[data-nit-m-final]', money(sub.price) + ' ' + curLabel());
        setTxt('[data-nit-m-discount]', '0.00 ' + curLabel());
        setDisp('[data-nit-m-offerrow]', 'none');
        var c = q('[data-nit-m-coupon]');
        if (c) {
          c.value = '';
        }
        setDisp('[data-nit-m-couponerr]', 'none');
        setDisp('[data-nit-m-error]', 'none');
        paintMethods(methodList);
        loadMethods(sub.currency);
        modal.style.display = 'flex';
        previewSub('');
      }

      function closeModal() {
        if (modal) {
          modal.style.display = 'none';
        }
        current = null;
      }
      if (modal) {
        var cancelBtn = q('[data-nit-m-cancel]');
        if (cancelBtn) {
          cancelBtn.addEventListener('click', closeModal);
        }
        modal.addEventListener('click', function(ev) {
          if (ev.target === modal) {
            closeModal();
          }
        });
        document.addEventListener('keydown', function(ev) {
          if (ev.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
          }
        });
        var applyBtn = q('[data-nit-m-apply]');
        if (applyBtn) {
          applyBtn.addEventListener('click', function() {
            previewSub(getVal('[data-nit-m-coupon]'));
          });
        }
        var proceedBtn = q('[data-nit-m-proceed]');
        if (proceedBtn) {
          proceedBtn.addEventListener('click', function() {
            if (!current) {
              return;
            }
            var btn = this;
            btn.disabled = true;
            var body = new URLSearchParams({
              function: 'create_subscription_checkout',
              sesskey: sesskey,
              subscriptionid: current.id,
              coupon_code: getVal('[data-nit-m-coupon]'),
              return_url: window.location.href,
              payment_method_id: methodId
            });
            fetch(subsUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
              })
              .then(function(r) {
                return r.json();
              })
              .then(function(res) {
                var data = (res && res.status === 'success' && res.data) ?
                  res.data : null;
                var pd = (data && data.payment_data) || {};
                if (data && pd.type === 'reference' && data.transaction_id) {
                  // Fawry, Meeza and the wallets answer with a code and no
                  // page, so the code screen is the result.
                  window.location.href = base +
                    '/local/payments/reference.php?id=' +
                    encodeURIComponent(data.transaction_id);
                } else if (data && data.checkout_url) {
                  window.location.href = data.checkout_url;
                } else {
                  throw new Error((res && res.error) ||
                    'checkout failed');
                }
              })
              .catch(function(e) {
                btn.disabled = false;
                setTxt('[data-nit-m-error]', e.message);
                setDisp('[data-nit-m-error]', '');
              });
          });
        }
      }

      // Delegated Subscribe click.
      root.addEventListener('click', function(ev) {
        var btn = ev.target.closest('[data-nit-buy]');
        if (!btn) {
          return;
        }
        // A guest or a signed-out visitor has nothing to confirm yet — send them to log in
        // rather than opening a checkout that the server would refuse anyway.
        if (!loggedIn) {
          window.location.href = loginUrl;
          return;
        }
        var card = btn.closest('[data-sub-id]');
        if (!card) {
          return;
        }
        openModal({
          id: card.getAttribute('data-sub-id'),
          name: card.getAttribute('data-sub-name'),
          days: card.getAttribute('data-sub-days'),
          price: parseFloat(card.getAttribute('data-sub-price')) || 0,
          currency: card.getAttribute('data-sub-currency') || 'EGP'
        });
      });

      // ── Load plans ──
      say('{mlang en}Loading…{mlang}{mlang ar}جاري التحميل…{mlang}');
      fetch(subsUrl + '?function=get_available_subscriptions', {
          headers: {
            'Accept': 'application/json'
          }
        })
        .then(function(r) {
          return r.json();
        })
        .then(function(res) {
          if (!res || res.status !== 'success') {
            throw new Error('load failed');
          }
          // The server's verdict on this visitor replaces the body-class guess, so a guest
          // is treated as signed out from here on (button → login, no "my subscription"
          // lookup) even though Moodle counts them as logged in.
          if (res.viewer) {
            loggedIn = !!res.viewer.loggedin;
            if (res.viewer.loginurl) {
              loginUrl = res.viewer.loginurl;
            }
          }
          var rows = res.data || [];
          if (!rows.length) {
            say(
              '{mlang en}No subscriptions available right now.{mlang}{mlang ar}لا توجد اشتراكات متاحة حالياً.{mlang}'
              );
            return;
          }
          if (msg) {
            msg.style.display = 'none';
          }

          rows.forEach(function(s, idx) {
            var desc = strip(s.description);
            var priceDisplay = moneyShort(s.price);
            var currency = s.currency || 'EGP';
            var html = tpl.innerHTML
              .replace(/\{\{name\}\}/g, esc(s.name))
              .replace(/\{\{price\}\}/g, esc(priceDisplay))
              .replace(/\{\{currency\}\}/g, esc(currency))
              .replace(/\{\{days\}\}/g, esc(s.duration_days))
              .replace(/\{\{courses\}\}/g, esc(s.courses_count))
              .replace(/\{\{description\}\}/g, esc(desc));

            var wrap = document.createElement('div');
            wrap.style.cssText =
              'flex: 1 1 280px; max-width: 310px; min-height: 420px; display: flex; flex-direction: column;';
            wrap.setAttribute('data-sub-id', s.id);
            wrap.setAttribute('data-sub-name', s.name);
            wrap.setAttribute('data-sub-days', s.duration_days);
            wrap.setAttribute('data-sub-price', s.price);
            wrap.setAttribute('data-sub-currency', currency);
            wrap.innerHTML = html;
            var inner = wrap.firstElementChild;

            // B2B badge.
            if (s.b2b_enabled) {
              var b = wrap.querySelector('[data-nit-b2b]');
              if (b) {
                b.style.display = 'inline-block';
              }
            }

            // How many courses the plan unlocks. The names themselves are on the details page.
            if (s.courses_count > 0) {
              var ci = wrap.querySelector('[data-nit-courses-item]');
              if (ci) {
                ci.style.display = 'flex';
              }
            }

            // Description (the plan's own description field), clamped by the template.
            if (desc) {
              var di = wrap.querySelector('[data-nit-desc]');
              if (di) {
                di.style.display = '-webkit-box';
              }
            }

            // Details link — same page whatever else the card ends up showing.
            var dlink = wrap.querySelector('[data-nit-details]');
            if (dlink) {
              dlink.href = base + '/local/nit_subscriptions/plan.php?id=' +
                encodeURIComponent(s.id);
            }

            // Offer: show strikethrough original + offer badge, display new price.
            if (s.offer_label && s.offer_final > 0) {
              var ofDiv = wrap.querySelector('[data-nit-offer-final]');
              var origEl = wrap.querySelector('[data-nit-orig-price]');
              var ofLabel = wrap.querySelector(
                '[data-nit-offer-label]');
              var priceEl = wrap.querySelector('[data-nit-price-val]');
              if (ofDiv && origEl && ofLabel && priceEl) {
                priceEl.textContent = moneyShort(s.offer_final);
                origEl.textContent = moneyShort(s.price);
                ofLabel.textContent = s.offer_label;
                ofDiv.style.display = '';
              }
            }

            // No profile country on a signed-in account: the server sends this plan with no
            // price, no offer and no seat tiers, because every price here is a country price.
            // The card shows the reason where the amount goes, and the Subscribe button
            // becomes the link that fixes it — checkout would refuse it anyway.
            if (s.country_required) {
              var crPrice = wrap.querySelector('[data-nit-price-val]');
              var crCur = wrap.querySelector('[data-nit-currency]');
              if (crPrice) {
                crPrice.textContent = s.country_short || '';
                crPrice.style.fontSize = '17px';
                crPrice.style.fontWeight = '700';
              }
              if (crCur) {
                crCur.style.display = 'none';
              }
              var crBtn = wrap.querySelector('[data-nit-buy]');
              if (crBtn && crBtn.parentNode) {
                var crLink = document.createElement('a');
                crLink.className = crBtn.className;
                crLink.setAttribute('style', (crBtn.getAttribute('style') || '') +
                  ';display:block; box-sizing:border-box; text-align:center; text-decoration:none;');
                crLink.href = s.country_url || (base + '/user/edit.php');
                crLink.textContent = s.country_action || '';
                crLink.title = s.country_message || '';
                crBtn.parentNode.replaceChild(crLink, crBtn);
              }
            }

            grid.appendChild(wrap);
          });

          if (loggedIn) {
            markActiveSubscription();
          }
        })
        .catch(function() {
          say(
            '{mlang en}Could not load subscriptions.{mlang}{mlang ar}تعذّر تحميل الاشتراكات.{mlang}'
            );
        });

      function markActiveSubscription() {
        fetch(subsUrl + '?function=get_my_active_subscription&sesskey=' +
            encodeURIComponent(sesskey), {
              headers: {
                'Accept': 'application/json'
              }
            })
          .then(function(r) {
            return r.json();
          })
          .then(function(res) {
            if (!res || res.status !== 'success' || !res.data || !res.data
              .has_active) {
              return;
            }
            var d = res.data;
            var activeId = String(d.subscriptionid);
            // Find the card that matches the active subscription and mark it.
            var cards = grid.querySelectorAll('[data-sub-id]');
            Array.prototype.forEach.call(cards, function(card) {
              if (card.getAttribute('data-sub-id') === activeId) {
                // This is the user's active card — highlight it.
                var inner = card.firstElementChild;
                if (inner) {
                  inner.style.border =
                    '2px solid var(--nit-brand-success)';
                  inner.style.boxShadow =
                    '0 10px 30px color-mix(in srgb, var(--nit-brand-success) 25%, transparent)';
                }
                // Show the subscribed badge.
                var badge = card.querySelector(
                  '[data-nit-subscribed-badge]');
                if (badge) {
                  badge.style.display = '';
                  // Add top padding so content doesn't overlap the badge.
                  if (inner) {
                    inner.style.paddingTop = '50px';
                  }
                }
                // The main button becomes the plan's status line — unless the plan is
                // close enough to its end that the admin's reminder window has opened,
                // in which case it becomes Renew. Renewing does not restart the clock:
                // the new period is added to the end of the current one, so nothing
                // already paid for is lost (see subscription_purchase_manager).
                var btn = card.querySelector('[data-nit-buy]');

                // "2 days left" on a renewed plan is a true number that reads like a
                // wrong one — it is the period running now PLUS the one already paid
                // for behind it. The number alone cannot explain itself, so it never
                // stands alone: an end DATE is always printed under it, and when a
                // renewal is stacked the line says that too.
                function statusNote() {
                  var p = document.createElement('p');
                  p.style.cssText =
                    'margin: 6px 0 0; font-size: 11.5px; line-height: 1.6; color: var(--nit-brand-textsecondary);';
                  var text = '';
                  if (d.expires_text) {
                    text =
                      '{mlang en}Access until{mlang}{mlang ar}الوصول حتى{mlang} ' +
                      d.expires_text;
                  }
                  if (d.renewed) {
                    text = (text ? (text + ' — ') : '') +
                      '{mlang en}includes the renewal you already paid for{mlang}{mlang ar}يشمل التجديد الذي دفعته بالفعل{mlang}';
                  }
                  p.textContent = text;
                  return text ? p : null;
                }

                if (btn && d.renew_due) {
                  btn.disabled = false;
                  btn.style.cursor = 'pointer';
                  btn.textContent =
                    '↻ {mlang en}Renew now{mlang}{mlang ar}جدّد الآن{mlang}';
                  btn.title = (d.days_left > 0) ?
                    (d.days_left +
                      ' {mlang en}days left{mlang}{mlang ar}يوم متبقٍ{mlang}') :
                    '';
                  // A short line under the button so the offer explains itself: the
                  // student is buying the NEXT period, not replacing this one.
                  var note = document.createElement('p');
                  note.style.cssText =
                    'margin: 6px 0 0; font-size: 11.5px; line-height: 1.6; color: var(--nit-brand-textsecondary);';
                  note.textContent = (d.expires_text ?
                      ('{mlang en}Access until{mlang}{mlang ar}الوصول حتى{mlang} ' +
                        d.expires_text + ' — ') : '') +
                    '{mlang en}renewing adds a full period on top.{mlang}{mlang ar}التجديد يضيف فترة كاملة فوقها.{mlang}';
                  btn.parentNode.appendChild(note);
                } else if (btn) {
                  btn.disabled = true;
                  btn.style.background =
                    'color-mix(in srgb, var(--nit-brand-success) 15%, transparent)';
                  btn.style.border =
                    '1px solid color-mix(in srgb, var(--nit-brand-success) 40%, transparent)';
                  btn.style.color = 'var(--nit-brand-success)';
                  btn.style.cursor = 'default';
                  btn.textContent = (d.days_left > 0) ?
                    ('✓ ' + d.days_left +
                      ' {mlang en}days left{mlang}{mlang ar}يوم متبقٍ{mlang}'
                    ) :
                    '✓ {mlang en}Active{mlang}{mlang ar}مفعّل{mlang}';
                  var statusline = statusNote();
                  if (statusline) {
                    btn.parentNode.appendChild(statusline);
                  }
                }
              } else {
                // Other cards: disable subscribe (one active allowed).
                var btn = card.querySelector('[data-nit-buy]');
                if (btn) {
                  btn.disabled = true;
                  btn.style.opacity = '0.45';
                  btn.style.cursor = 'default';
                }
              }
            });
          })
          .catch(function() {});
      }
    })();
