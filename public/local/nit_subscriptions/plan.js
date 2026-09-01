// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Checkout for the public plan-details page (local/nit_subscriptions/plan.php).
 *
 * Same two calls the home-page block makes — preview_discount prices a coupon, then
 * create_subscription_checkout hands the visitor to the gateway. Every label the dialog needs
 * is already in the markup; the only text this file writes is money and the one coupon error.
 *
 * @module     local_nit_subscriptions/plan
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var root = document.querySelector('[data-nitplan]');
        var modal = root && root.querySelector('[data-nitplan-modal]');
        var openBtn = root && root.querySelector('[data-nitplan-buy]');
        if (!root || !modal || !openBtn) {
            return;
        }

        var cfg;
        try {
            cfg = JSON.parse(root.getAttribute('data-nitplan'));
        } catch (e) {
            return;
        }

        /**
         * An element inside the dialog.
         *
         * @param {String} sel attribute selector
         * @return {Element|null}
         */
        var q = function(sel) {
            return modal.querySelector(sel);
        };

        /**
         * An amount with the plan's currency, matching the server-rendered price panel.
         *
         * @param {Number} n
         * @return {String}
         */
        var money = function(n) {
            var v = (Math.round(Number(n) * 100) / 100).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            return cfg.currency ? (v + ' ' + cfg.currency) : v;
        };

        /**
         * Write text into a dialog slot.
         *
         * @param {String} sel
         * @param {String} val
         */
        var setTxt = function(sel, val) {
            var el = q(sel);
            if (el) {
                el.textContent = val;
            }
        };

        /**
         * Show or hide a dialog slot, and fill it when showing.
         *
         * @param {String} sel
         * @param {Boolean} show
         * @param {String} text
         */
        var toggle = function(sel, show, text) {
            var el = q(sel);
            if (!el) {
                return;
            }
            if (show) {
                if (text !== undefined) {
                    el.textContent = text;
                }
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
        };

        /**
         * Price the plan with (or without) a coupon and repaint the summary rows.
         *
         * @param {String} code coupon code, '' for the plain price
         */
        var preview = function(code) {
            var url = cfg.commerceurl +
                '?function=preview_discount&item_type=subscription&item_id=' + encodeURIComponent(cfg.planid) +
                '&coupon_code=' + encodeURIComponent(code || '') +
                '&sesskey=' + encodeURIComponent(cfg.sesskey);

            fetch(url, {headers: {Accept: 'application/json'}})
                .then(function(r) {
                    return r.json();
                })
                .then(function(res) {
                    if (!res || res.status !== 'success') {
                        throw new Error('preview failed');
                    }
                    var d = res.data || {};
                    setTxt('[data-nitplan-original]', money(d.original || 0));
                    setTxt('[data-nitplan-discount]', money(d.discount || 0));
                    setTxt('[data-nitplan-final]', money(d.final || 0));

                    var offer = Number(d.offer_discount || 0);
                    var name = (d.offers && d.offers[0] && d.offers[0].name) ? d.offers[0].name : (d.offer_name || '');
                    toggle('[data-nitplan-offerrow]', offer > 0,
                        '-' + money(offer) + (name ? ('  (' + name + ')') : ''));

                    toggle('[data-nitplan-couponerr]', !!d.coupon_error, d.coupon_error || '');
                })
                .catch(function() {
                    toggle('[data-nitplan-couponerr]', true, cfg.couponfailed);
                });
        };

        /**
         * Close the dialog.
         */
        var close = function() {
            modal.setAttribute('hidden', '');
        };

        openBtn.addEventListener('click', function() {
            var input = q('[data-nitplan-coupon]');
            if (input) {
                input.value = '';
            }
            toggle('[data-nitplan-couponerr]', false);
            toggle('[data-nitplan-error]', false);
            toggle('[data-nitplan-offerrow]', false);
            modal.removeAttribute('hidden');
            preview('');
        });

        var cancel = q('[data-nitplan-cancel]');
        if (cancel) {
            cancel.addEventListener('click', close);
        }

        modal.addEventListener('click', function(ev) {
            if (ev.target === modal) {
                close();
            }
        });

        document.addEventListener('keydown', function(ev) {
            if (ev.key === 'Escape' && !modal.hasAttribute('hidden')) {
                close();
            }
        });

        // Which method the visitor picked. 0 means the dialog never asked, and
        // the gateway then chooses for itself exactly as it used to.
        var methodid = 0;

        /**
         * Draw the payment methods, if there is a choice worth making.
         *
         * One method is not a choice, so the strip stays hidden and the gateway
         * is charged directly — same rule as the course checkout and the app.
         *
         * @return {void}
         */
        var buildMethods = function() {
            var wrap = q('[data-nitplan-methods]');
            var list = q('[data-nitplan-methodslist]');
            var label = q('[data-nitplan-methodslabel]');
            var methods = (cfg.methods || []);
            if (!wrap || !list || methods.length < 2) {
                return;
            }

            if (label) {
                label.textContent = cfg.methodlabel || '';
            }
            list.textContent = '';

            var cards = [];
            var select = function(i) {
                methodid = Number(methods[i].id) || 0;
                cards.forEach(function(c, n) {
                    c.classList.toggle('is-selected', n === i);
                    c.setAttribute('aria-pressed', n === i ? 'true' : 'false');
                });
            };

            methods.forEach(function(m, i) {
                var card = document.createElement('button');
                card.type = 'button';
                card.className = 'nitplan__method';

                if (m.logo) {
                    var img = document.createElement('img');
                    img.src = m.logo;
                    img.alt = '';
                    // A broken logo costs the card its picture, not its label.
                    img.addEventListener('error', function() {
                        img.style.display = 'none';
                    });
                    card.appendChild(img);
                }

                var name = document.createElement('span');
                name.textContent = m.name || '';
                card.appendChild(name);

                if (m.is_reference) {
                    var hint = document.createElement('small');
                    hint.textContent = cfg.methodcode || '';
                    card.appendChild(hint);
                }

                card.addEventListener('click', function() {
                    select(i);
                });
                cards.push(card);
                list.appendChild(card);
            });

            select(0);
            wrap.removeAttribute('hidden');
        };

        buildMethods();

        var apply = q('[data-nitplan-apply]');
        if (apply) {
            apply.addEventListener('click', function() {
                var input = q('[data-nitplan-coupon]');
                preview(input ? String(input.value || '').trim() : '');
            });
        }

        var proceed = q('[data-nitplan-proceed]');
        if (proceed) {
            proceed.addEventListener('click', function() {
                var input = q('[data-nitplan-coupon]');
                proceed.disabled = true;
                toggle('[data-nitplan-error]', false);

                var body = new URLSearchParams({
                    'function': 'create_subscription_checkout',
                    sesskey: cfg.sesskey,
                    subscriptionid: cfg.planid,
                    coupon_code: input ? String(input.value || '').trim() : '',
                    return_url: cfg.returnurl,
                    payment_method_id: methodid
                });

                fetch(cfg.subsurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                })
                    .then(function(r) {
                        return r.json();
                    })
                    .then(function(res) {
                        var data = (res && res.status === 'success' && res.data) ? res.data : null;
                        if (data) {
                            // Fawry, Meeza and the wallets answer with a code
                            // instead of a page, so there is nothing to redirect
                            // to — the code screen is the result.
                            var pd = data.payment_data || {};
                            if (pd.type === 'reference' && data.transaction_id) {
                                window.location.href = cfg.referenceurl + '?id=' +
                                    encodeURIComponent(data.transaction_id);
                                return;
                            }
                            if (data.checkout_url) {
                                window.location.href = data.checkout_url;
                                return;
                            }
                        }
                        throw new Error((res && res.error) || 'checkout failed');
                    })
                    .catch(function(e) {
                        proceed.disabled = false;
                        toggle('[data-nitplan-error]', true, e.message);
                    });
            });
        }
    });
})();
