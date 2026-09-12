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
 * The shared financial-report panel.
 *
 * Every "Reports" tab on the platform is this file painting the same skeleton from the same
 * endpoint. Nothing here knows which page it is on — the scope it was booted with is the only
 * difference between the coupon report, the course report and the master one.
 *
 * Plain DOM rather than a module: the pages it lives in are server-rendered admin screens that
 * already load their own script this way, and one <script> is one fewer thing that can be stale
 * after a deploy.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    var CFG = window.NITFR_BOOT;
    if (!CFG) {
        return;
    }

    var STR = CFG.str || {};
    var root = null;
    var loaded = false;
    var busy = false;

    // Current query. Everything the user can change lives here and nowhere else, so a reload,
    // a drill-down and an export always describe the same report.
    var state = {
        scope: CFG.scope,
        state: 'paid',
        from: '',
        to: '',
        q: '',
        currency: '',
        itemid: 0,
        source: '',
        page: 0,
        perpage: CFG.perpage || 25
    };

    var last = null;

    function str(key) {
        return Object.prototype.hasOwnProperty.call(STR, key) ? STR[key] : key;
    }

    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"]/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c];
        });
    }

    function part(name) {
        return root ? root.querySelector('[data-nitfr="' + name + '"]') : null;
    }

    /**
     * A money amount, bidi-isolated.
     *
     * Arabic is the first language of this platform, and in an RTL paragraph "-187.80" is a
     * neutral hyphen followed by a left-to-right digit run: the browser reorders it and the
     * minus sign ends up at the far side of the cell, detached from the number it negates —
     * a discount that reads as though it were not one. <bdi dir="ltr"> pins the whole amount
     * to one direction without disturbing how the cell itself aligns.
     *
     * @param {string} text a pre-formatted amount from the server
     * @param {boolean} negative render it as money going out
     * @return {string} HTML
     */
    function money(text, negative) {
        // "-0.00" is not a smaller number than zero, it just looks like a mistake, so the sign
        // is only drawn when there is actually something being taken away.
        var sign = (negative && !/^[0.,]*$/.test(String(text))) ? '-' : '';
        return '<bdi dir="ltr">' + sign + esc(text) + '</bdi>';
    }

    /** A zero that reads as "nothing here", not as a figure worth looking at. */
    function zero() {
        return '<span class="nitfr-muted"><bdi dir="ltr">0.00</bdi></span>';
    }

    function el(tag, className, html) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (html !== undefined) {
            node.innerHTML = html;
        }
        return node;
    }

    // -- Filters ---------------------------------------------------------------------

    function field(label, control) {
        var wrap = el('div', 'nitfr-field');
        var id = 'nitfr-' + label.replace(/\W+/g, '') + '-' + Math.random().toString(36).slice(2, 7);
        control.id = id;
        var lab = el('label', null, esc(label));
        lab.setAttribute('for', id);
        wrap.appendChild(lab);
        wrap.appendChild(control);
        return wrap;
    }

    function select(options, value) {
        var node = el('select', 'form-control');
        options.forEach(function (opt) {
            var o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.label;
            if (String(opt.value) === String(value)) {
                o.selected = true;
            }
            node.appendChild(o);
        });
        return node;
    }

    function input(type, value, placeholder) {
        var node = el('input', 'form-control');
        node.type = type;
        node.value = value || '';
        if (placeholder) {
            node.placeholder = placeholder;
        }
        return node;
    }

    /** Run the report under whatever the filter bar now says, from the first page. */
    function apply() {
        state.page = 0;
        load();
    }

    /**
     * Build the filter bar. The "narrow to" dropdown is the only part that changes between
     * scopes, and it simply disappears where the scope has nothing to narrow to.
     */
    function renderFilters() {
        var form = part('filters');
        if (!form) {
            return;
        }
        form.innerHTML = '';

        var narrowables = (CFG.options || {})[state.scope];
        if (narrowables && narrowables.length) {
            var opts = [{value: 0, label: str('rep_all')}];
            narrowables.forEach(function (item) {
                opts.push({value: item.id, label: item.name});
            });
            var itemsel = select(opts, state.itemid);
            itemsel.addEventListener('change', function () {
                state.itemid = parseInt(this.value, 10) || 0;
            });
            form.appendChild(field(str('rep_narrow'), itemsel));
        }

        var from = input('date', state.from);
        from.addEventListener('change', function () {
            state.from = this.value;
        });
        form.appendChild(field(str('rep_from'), from));

        var to = input('date', state.to);
        to.addEventListener('change', function () {
            state.to = this.value;
        });
        form.appendChild(field(str('rep_to'), to));

        // The refunds scope is about money that went back out; offering to look at anything
        // else there would produce an empty table and a puzzled admin.
        if (state.scope !== 'refunds') {
            var states = select([
                {value: 'paid', label: str('rep_state_paid')},
                {value: 'completed', label: str('rep_state_completed')},
                {value: 'refunded', label: str('rep_state_refunded')},
                {value: 'lost', label: str('rep_state_lost')},
                {value: 'all', label: str('rep_state_all')}
            ], state.state);
            states.addEventListener('change', function () {
                state.state = this.value;
            });
            form.appendChild(field(str('rep_state'), states));
        }

        // Only worth a control on a site that actually sells in more than one currency.
        if (last && last.currencies && last.currencies.length > 1) {
            var cur = select(last.currencies.map(function (code) {
                return {value: code, label: code};
            }), last.currency);
            cur.addEventListener('change', function () {
                state.currency = this.value;
            });
            form.appendChild(field(str('rep_currency'), cur));
        }

        var q = input('search', state.q, str('rep_search_ph'));
        q.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                apply();
            }
        });
        var qfield = field(str('rep_search'), q);
        qfield.className = 'nitfr-field nitfr-field--grow';
        form.appendChild(qfield);

        var actions = el('div', 'nitfr-actions');

        var applybtn = el('button', 'btn btn-primary', esc(str('rep_apply')));
        applybtn.type = 'button';
        applybtn.addEventListener('click', function () {
            state.q = q.value;
            apply();
        });
        actions.appendChild(applybtn);

        var resetbtn = el('button', 'btn btn-link', esc(str('rep_reset')));
        resetbtn.type = 'button';
        resetbtn.addEventListener('click', function () {
            state.from = state.to = state.q = state.currency = state.source = '';
            state.itemid = 0;
            state.state = 'paid';
            state.page = 0;
            renderFilters();
            load();
        });
        actions.appendChild(resetbtn);

        var exportbtn = el('a', 'btn btn-secondary', esc(str('rep_export')));
        exportbtn.href = '#';
        exportbtn.addEventListener('click', function (event) {
            event.preventDefault();
            state.q = q.value;
            window.location.href = CFG.endpoint + '?' + query({format: 'csv', perpage: 0, page: 0});
        });
        actions.appendChild(exportbtn);

        form.appendChild(actions);
    }

    function renderIntro() {
        var node = part('intro');
        var text = (CFG.intros || {})[state.scope];
        if (node && text) {
            node.textContent = text;
        }
    }

    function renderScopes() {
        var nav = part('scopes');
        if (!nav || !CFG.scopes || !CFG.scopes.length) {
            return;
        }
        nav.hidden = false;
        nav.innerHTML = '';
        CFG.scopes.forEach(function (scope) {
            var btn = el('button', scope.key === state.scope ? 'is-active' : '', esc(scope.label));
            btn.type = 'button';
            btn.addEventListener('click', function () {
                if (scope.key === state.scope) {
                    return;
                }
                state.scope = scope.key;
                state.itemid = 0;
                state.source = '';
                state.page = 0;
                if (scope.key === 'refunds') {
                    state.state = 'refunded';
                } else if (state.state === 'refunded') {
                    state.state = 'paid';
                }
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + scope.key);
                }
                renderScopes();
                renderIntro();
                renderFilters();
                load();
            });
            nav.appendChild(btn);
        });
    }

    // -- Painting --------------------------------------------------------------------

    function kpi(label, value, currency, help, modifier) {
        return '<div class="nitfr-kpi' + (modifier ? ' nitfr-kpi--' + modifier : '') + '">' +
            '<span class="nitfr-kpi__label">' + esc(label) + '</span>' +
            '<span class="nitfr-kpi__value">' + value +
            (currency ? '<span class="nitfr-kpi__cur">' + esc(currency) + '</span>' : '') + '</span>' +
            '<span class="nitfr-kpi__help">' + esc(help) + '</span>' +
            '</div>';
    }

    function renderKpis(data) {
        var box = part('kpis');
        if (!box) {
            return;
        }
        var k = data.kpis;
        var cur = data.currency;
        box.innerHTML =
            kpi(str('rep_kpi_gross'), money(k.grossf), cur, str('rep_kpi_gross_help')) +
            kpi(str('rep_kpi_discount'), money(k.discountf, true), cur, str('rep_kpi_discount_help')) +
            kpi(str('rep_kpi_net'), money(k.netf), cur, str('rep_kpi_net_help'), 'net') +
            kpi(str('rep_kpi_refunded'), money(k.refundedf, true), cur, str('rep_kpi_refunded_help'), 'refund') +
            kpi(str('rep_kpi_earned'), money(k.earnedf), cur, str('rep_kpi_earned_help'), 'earned') +
            // Only where there is something to say: in the default "paid orders" view every
            // order paid, and a permanent 0.00 card is a column of noise on five pages.
            (hasLost(data)
                ? kpi(str('rep_kpi_lost'), money(k.lostf), cur,
                    strf('rep_kpi_lost_help', {orders: k.lostorders}), 'lost')
                : '') +
            kpi(str('rep_kpi_orders'), '<bdi dir="ltr">' + esc(String(k.orders)) + '</bdi>', '',
                strf('rep_kpi_orders_help', {learners: k.learners, average: k.averagef + ' ' + cur}));
    }

    // Abandoned or failed baskets are only in range when the admin asks for a state that
    // includes them, so the column and the card that describe them come and go together.
    function hasLost(data) {
        return !!(data && data.kpis && data.kpis.lostorders > 0);
    }

    function strf(key, params) {
        var text = str(key);
        Object.keys(params || {}).forEach(function (name) {
            text = text.split('{$a->' + name + '}').join(params[name]);
        });
        return text.split('{$a}').join(params && params.value !== undefined ? params.value : '');
    }

    function bar(share) {
        var pct = Math.max(2, Math.min(100, Math.round(share * 100)));
        return '<span class="nitfr-bar__track"><span class="nitfr-bar" style="width:' + pct + '%"></span></span>';
    }

    function renderBreakdown(data) {
        var heading = part('breakdownheading');
        if (heading) {
            heading.textContent = data.breakdownlabel;
        }

        var table = part('breakdown');
        if (!table) {
            return;
        }
        var cur = data.currency;
        var lost = hasLost(data);
        var cols = lost ? 10 : 9;
        var head = '<tr>' +
            '<th>' + esc(data.breakdownlabel) + '</th>' +
            '<th>' + esc(str('rep_col_share')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_orders')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_learners')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_gross')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_discount')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_net')) + '</th>' +
            (lost ? '<th class="nitfr-num">' + esc(str('rep_col_lost')) + '</th>' : '') +
            '<th class="nitfr-num">' + esc(str('rep_col_refunded')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_earned')) + '</th>' +
            '</tr>';
        table.querySelector('thead').innerHTML = head;

        var body = table.querySelector('tbody');
        if (!data.breakdown.length) {
            body.innerHTML = '<tr><td colspan="' + cols + '">' + esc(str('rep_none')) + '</td></tr>';
            table.querySelector('tfoot').innerHTML = '';
            return;
        }

        // The share bar is measured against the biggest earner, not the total, so the leading
        // row always fills the bar and the rest read as "how close to the leader".
        var top = data.breakdown.reduce(function (max, row) {
            return Math.max(max, Math.abs(row.earned));
        }, 0) || 1;

        // Only the master "all" view drills down: elsewhere the breakdown key is a name, not
        // a source the detail table can be filtered by.
        var drillable = state.scope === 'all';

        body.innerHTML = data.breakdown.map(function (row) {
            return '<tr' + (drillable ? ' class="nitfr-clickable" data-source="' + esc(row.key) + '"' : '') + '>' +
                '<td>' + esc(row.label) + '</td>' +
                '<td>' + bar(Math.abs(row.earned) / top) + '</td>' +
                '<td class="nitfr-num">' + money(String(row.orders)) + '</td>' +
                '<td class="nitfr-num">' + money(String(row.learners)) + '</td>' +
                '<td class="nitfr-num">' + money(row.grossf) + '</td>' +
                '<td class="nitfr-num">' + (row.discount > 0 ? money(row.discountf, true) : zero()) + '</td>' +
                '<td class="nitfr-num">' + money(row.netf) + '</td>' +
                (lost ? '<td class="nitfr-num nitfr-lost">' + money(row.lostf) + '</td>' : '') +
                '<td class="nitfr-num">' + (row.refunded > 0 ? money(row.refundedf, true) : zero()) + '</td>' +
                '<td class="nitfr-num"><strong>' + money(row.earnedf) + '</strong></td>' +
                '</tr>';
        }).join('');

        if (drillable) {
            Array.prototype.forEach.call(body.querySelectorAll('[data-source]'), function (tr) {
                tr.addEventListener('click', function () {
                    state.source = this.getAttribute('data-source');
                    state.page = 0;
                    load();
                });
            });
        }

        var k = data.kpis;
        table.querySelector('tfoot').innerHTML = '<tr>' +
            '<td>' + esc(str('rep_col_total')) + '</td><td></td>' +
            '<td class="nitfr-num">' + money(String(k.orders)) + '</td>' +
            '<td class="nitfr-num">' + money(String(k.learners)) + '</td>' +
            '<td class="nitfr-num">' + money(k.grossf) + '</td>' +
            '<td class="nitfr-num">' + (k.discount > 0 ? money(k.discountf, true) : zero()) + '</td>' +
            '<td class="nitfr-num">' + money(k.netf) + '</td>' +
            (lost ? '<td class="nitfr-num nitfr-lost">' + money(k.lostf) + '</td>' : '') +
            '<td class="nitfr-num">' + (k.refunded > 0 ? money(k.refundedf, true) : zero()) + '</td>' +
            '<td class="nitfr-num">' + money(k.earnedf) + ' ' + esc(cur) + '</td>' +
            '</tr>';
    }

    function renderRows(data) {
        var table = part('rows');
        if (!table) {
            return;
        }
        var lost = hasLost(data);
        table.querySelector('thead').innerHTML = '<tr>' +
            '<th>' + esc(str('rep_col_date')) + '</th>' +
            '<th>' + esc(str('rep_col_order')) + '</th>' +
            '<th>' + esc(str('rep_col_user')) + '</th>' +
            '<th>' + esc(str('rep_col_item')) + '</th>' +
            '<th>' + esc(str('rep_col_source')) + '</th>' +
            '<th>' + esc(str('rep_col_detail')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_gross')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_discount')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_net')) + '</th>' +
            (lost ? '<th class="nitfr-num">' + esc(str('rep_col_lost')) + '</th>' : '') +
            '<th class="nitfr-num">' + esc(str('rep_col_refunded')) + '</th>' +
            '<th class="nitfr-num">' + esc(str('rep_col_earned')) + '</th>' +
            '<th>' + esc(str('rep_col_status')) + '</th>' +
            '</tr>';

        var body = table.querySelector('tbody');
        if (!data.rows.length) {
            body.innerHTML = '<tr><td colspan="' + (lost ? 13 : 12) + '">' +
                esc(str('rep_none')) + '</td></tr>';
            return;
        }

        body.innerHTML = data.rows.map(function (row) {
            var refund = row.refunded > 0
                ? money(row.refundedf, true) +
                  (row.refundkindlabel
                      ? '<span class="nitfr-sub">' + esc(row.refundkindlabel) + '</span>'
                      : '')
                : zero();

            return '<tr' + (row.paid ? '' : ' class="nitfr-row--lost"') + '>' +
                '<td>' + esc(row.timeformatted) + '</td>' +
                '<td><span class="nitfr-muted"><bdi dir="ltr">' + esc(row.order) + '</bdi></span></td>' +
                '<td>' + esc(row.user) + '<span class="nitfr-sub"><bdi>' + esc(row.email) + '</bdi></span></td>' +
                '<td>' + esc(row.item) + '</td>' +
                '<td><span class="nitfr-badge nitfr-badge--' + esc(row.channel) + '">' +
                    esc(row.sourcelabel) + '</span></td>' +
                '<td class="nitfr-muted">' + esc(row.detail) + '</td>' +
                '<td class="nitfr-num">' + (row.paid ? money(row.grossf) : zero()) + '</td>' +
                '<td class="nitfr-num">' + (row.discount > 0 ? money(row.discountf, true) : zero()) + '</td>' +
                '<td class="nitfr-num">' + (row.paid ? money(row.netf) : zero()) + '</td>' +
                (lost ? '<td class="nitfr-num nitfr-lost">' + (row.paid ? zero() : money(row.lostf)) + '</td>' : '') +
                '<td class="nitfr-num">' + refund + '</td>' +
                '<td class="nitfr-num"><strong>' + money(row.earnedf) + '</strong></td>' +
                '<td>' + esc(row.statuslabel) + '</td>' +
                '</tr>';
        }).join('');
    }

    function renderPager(data) {
        var box = part('pager');
        if (!box) {
            return;
        }
        box.innerHTML = '';
        if (!data.total) {
            return;
        }

        var first = data.page * data.perpage + 1;
        var lastrow = Math.min(data.total, (data.page + 1) * data.perpage);
        box.appendChild(el('span', 'nitfr-pager__info',
            esc(strf('rep_pager', {first: first, last: lastrow, total: data.total}))));

        var pages = Math.ceil(data.total / data.perpage);
        if (pages <= 1) {
            return;
        }

        function pagebtn(label, target, disabled, active) {
            var btn = el('button', active ? 'is-active' : '', esc(label));
            btn.type = 'button';
            btn.disabled = !!disabled;
            if (!disabled) {
                btn.addEventListener('click', function () {
                    state.page = target;
                    load();
                });
            }
            box.appendChild(btn);
        }

        pagebtn(str('rep_prev'), data.page - 1, data.page === 0, false);

        // A window around the current page: a year of orders must not print 200 buttons.
        var start = Math.max(0, data.page - 2);
        var end = Math.min(pages - 1, start + 4);
        start = Math.max(0, end - 4);
        for (var i = start; i <= end; i++) {
            pagebtn(String(i + 1), i, false, i === data.page);
        }

        pagebtn(str('rep_next'), data.page + 1, data.page >= pages - 1, false);
    }

    function renderDrill() {
        var box = part('drill');
        if (!box) {
            return;
        }
        if (!state.source) {
            box.hidden = true;
            box.innerHTML = '';
            return;
        }
        box.hidden = false;
        box.innerHTML = '<span>' + esc(last ? sourceLabel(state.source) : state.source) + '</span>';
        var clear = el('button', 'btn btn-sm btn-link', esc(str('rep_clearsource')));
        clear.type = 'button';
        clear.addEventListener('click', function () {
            state.source = '';
            state.page = 0;
            load();
        });
        box.appendChild(clear);
    }

    function sourceLabel(key) {
        var found = key;
        (last.breakdown || []).forEach(function (row) {
            if (row.key === key) {
                found = row.label;
            }
        });
        return found;
    }

    // -- Loading ---------------------------------------------------------------------

    function query(overrides) {
        var params = new URLSearchParams();
        params.append('sesskey', CFG.sesskey);
        var merged = Object.assign({}, state, overrides || {});
        Object.keys(merged).forEach(function (key) {
            var value = merged[key];
            if (value !== '' && value !== 0 && value !== null && value !== undefined) {
                params.append(key, value);
            }
        });
        // page=0 is meaningful and would be dropped by the test above.
        if (!params.has('page')) {
            params.append('page', '0');
        }
        if (!params.has('scope')) {
            params.append('scope', state.scope);
        }
        return params.toString();
    }

    function message(text) {
        var box = part('msg');
        if (!box) {
            return;
        }
        box.hidden = !text;
        box.textContent = text || '';
    }

    function load() {
        if (busy) {
            return;
        }
        busy = true;
        loaded = true;
        if (root) {
            root.classList.add('nitfr-busy');
        }
        message('');

        fetch(CFG.endpoint + '?' + query(), {credentials: 'same-origin'})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status);
                }
                return response.json();
            })
            .then(function (data) {
                var currencychange = !last || (last.currencies || []).length !== (data.currencies || []).length;
                last = data;
                state.page = data.page;
                // Rebuilt only when the currency selector needs to appear or disappear:
                // redrawing the bar on every load would throw away whatever the admin is
                // halfway through typing in the search box.
                if (currencychange) {
                    renderFilters();
                }
                renderDrill();
                renderKpis(data);
                renderBreakdown(data);
                renderRows(data);
                renderPager(data);
                message(data.truncated ? str('rep_truncated') : '');
            })
            .catch(function () {
                message(str('rep_error'));
            })
            .then(function () {
                busy = false;
                if (root) {
                    root.classList.remove('nitfr-busy');
                }
            });
    }

    function visible(node) {
        return !!(node && node.offsetParent !== null);
    }

    function init() {
        root = document.querySelector('.nitfr');
        if (!root) {
            return;
        }
        var hash = (window.location.hash || '').replace('#', '');
        if (CFG.scopes && CFG.scopes.length) {
            CFG.scopes.forEach(function (scope) {
                if (scope.key === hash) {
                    state.scope = scope.key;
                    if (scope.key === 'refunds') {
                        state.state = 'refunded';
                    }
                }
            });
        }
        renderScopes();
        renderIntro();
        renderFilters();

        // The master page shows the panel straight away; on the four tabbed pages the host
        // calls ensure() when its Reports tab is opened, so a page nobody reports from costs
        // no query at all.
        if (visible(root)) {
            load();
        }
    }

    window.NITFR = {
        ensure: function () {
            if (!loaded) {
                load();
            }
        },
        reload: function () {
            state.page = 0;
            load();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
