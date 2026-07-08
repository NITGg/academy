/*
 * Academy shared front-end UI helpers.
 *
 * Loaded as a plain (non-AMD) script via $PAGE->requires->js('/local/academy/ui.js', true) so the
 * per-page inline IIFEs can reuse it. Currently exposes a single reusable client-side paginator that
 * renders a unified pagination bar (styled by .acad-pager in styles.css) beneath any list/table.
 *
 * Usage:
 *   var pager = AcademyUI.paginate({
 *       rows: allRows,                 // full data array
 *       pageSize: 10,                  // rows per page (default 10)
 *       render: function (pageRows) {  // fills the content container for the given page
 *           tbody.innerHTML = '';
 *           pageRows.forEach(...);
 *       },
 *       pagerEl: document.getElementById('my-pager'), // where the bar is drawn
 *       labels: { info: 'Showing {from}–{to} of {total}', prev: 'Prev', next: 'Next' }
 *   });
 *   // later, when data changes:
 *   pager.setRows(newRows);
 */
(function (w) {
    'use strict';

    var DEFAULT_LABELS = {
        info: 'Showing {from}–{to} of {total}',
        prev: '‹',
        next: '›'
    };

    function fill(tpl, map) {
        return String(tpl).replace(/\{(\w+)\}/g, function (m, k) {
            return (k in map) ? map[k] : m;
        });
    }

    // Build the list of page tokens to show: 1 … around-current … last, capped so the bar stays short.
    function pageTokens(current, total) {
        if (total <= 7) {
            var all = [];
            for (var i = 1; i <= total; i++) { all.push(i); }
            return all;
        }
        var tokens = [1];
        var start = Math.max(2, current - 1);
        var end = Math.min(total - 1, current + 1);
        if (start > 2) { tokens.push('...'); }
        for (var p = start; p <= end; p++) { tokens.push(p); }
        if (end < total - 1) { tokens.push('...'); }
        tokens.push(total);
        return tokens;
    }

    function paginate(opts) {
        var rows = opts.rows || [];
        var pageSize = opts.pageSize || 10;
        var render = opts.render;
        var pagerEl = opts.pagerEl;
        var labels = Object.assign({}, DEFAULT_LABELS, opts.labels || {});
        var current = 1;

        function totalPages() {
            return Math.max(1, Math.ceil(rows.length / pageSize));
        }

        function btn(label, opts2) {
            opts2 = opts2 || {};
            var b = document.createElement('button');
            b.type = 'button';
            b.innerHTML = label;
            if (opts2.cls) { b.className = opts2.cls; }
            if (opts2.disabled) { b.disabled = true; }
            if (opts2.onclick) { b.onclick = opts2.onclick; }
            if (opts2.label) { b.setAttribute('aria-label', opts2.label); }
            return b;
        }

        function go(p) {
            var tp = totalPages();
            current = Math.min(Math.max(1, p), tp);
            var startIdx = (current - 1) * pageSize;
            render(rows.slice(startIdx, startIdx + pageSize), current);
            drawBar();
        }

        function drawBar() {
            if (!pagerEl) { return; }
            pagerEl.innerHTML = '';
            var tp = totalPages();
            if (rows.length === 0) { return; }

            var from = (current - 1) * pageSize + 1;
            var to = Math.min(current * pageSize, rows.length);
            var info = document.createElement('span');
            info.className = 'acad-pager__info';
            info.textContent = fill(labels.info, { from: from, to: to, total: rows.length });
            pagerEl.appendChild(info);

            if (tp <= 1) { return; } // one page: keep the "Showing all" info, drop the controls

            pagerEl.appendChild(btn(labels.prev, {
                disabled: current === 1, label: 'Previous page',
                onclick: function () { go(current - 1); }
            }));

            pageTokens(current, tp).forEach(function (tok) {
                if (tok === '...') {
                    var e = document.createElement('span');
                    e.className = 'acad-pager__ellipsis';
                    e.textContent = '…';
                    pagerEl.appendChild(e);
                    return;
                }
                pagerEl.appendChild(btn(String(tok), {
                    cls: tok === current ? 'is-active' : '',
                    onclick: function () { go(tok); }
                }));
            });

            pagerEl.appendChild(btn(labels.next, {
                disabled: current === tp, label: 'Next page',
                onclick: function () { go(current + 1); }
            }));
        }

        go(1);

        return {
            setRows: function (newRows) { rows = newRows || []; go(1); },
            refresh: function () { go(current); },
            get page() { return current; }
        };
    }

    w.AcademyUI = w.AcademyUI || {};
    w.AcademyUI.paginate = paginate;

    function pickerEsc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    /*
     * Reusable search-and-pick combobox — replaces raw numeric-id inputs on the admin pages.
     *
     * Renders a search box into `mount`; as the admin types (debounced) it calls `search(query)` and
     * shows a dropdown of items. Choosing one collapses the box into a removable chip and stores it.
     * `primary`/`secondary` map an item to its two display lines; `idOf` maps it to the stored id.
     *
     * Usage:
     *   var p = AcademyUI.picker({
     *       mount: el, placeholder: '…',
     *       search: function (q) { return apiGet('search_users', { query: q }); }, // -> Promise<items>
     *       primary: function (u) { return u.fullname; },
     *       secondary: function (u) { return u.email; },
     *       onChange: function (item) { ... },  // optional; item is null when cleared
     *       labels: { searching: '…', none: 'No matches', hint: 'Type 2+ characters' }
     *   });
     *   p.value(); // selected id as string, or '' if none
     */
    function picker(opts) {
        var labels = Object.assign({ searching: 'Searching…', none: 'No matches', hint: 'Type 2 or more characters' }, opts.labels || {});
        var primary = opts.primary || function (x) { return x.fullname; };
        var secondary = opts.secondary || function (x) { return x.email || ''; };
        var idOf = opts.idOf || function (x) { return x.id; };

        var root = document.createElement('div');
        root.className = 'acad-picker';
        root.innerHTML =
            '<input type="text" class="form-control acad-picker__input" autocomplete="off" placeholder="' +
                (opts.placeholder ? String(opts.placeholder).replace(/"/g, '&quot;') : '') + '">' +
            '<div class="acad-picker__menu" hidden></div>' +
            '<div class="acad-picker__chip" hidden></div>';
        opts.mount.appendChild(root);

        var input = root.querySelector('.acad-picker__input');
        var menu = root.querySelector('.acad-picker__menu');
        var chip = root.querySelector('.acad-picker__chip');
        var selected = null, activeIdx = -1, items = [], timer = null, seq = 0;

        function closeMenu() { menu.hidden = true; menu.innerHTML = ''; items = []; activeIdx = -1; }
        function showMessage(text) { menu.hidden = false; menu.innerHTML = '<div class="acad-picker__msg">' + pickerEsc(text) + '</div>'; items = []; activeIdx = -1; }

        function renderMenu(list) {
            items = list || [];
            if (!items.length) { showMessage(labels.none); return; }
            menu.innerHTML = items.map(function (it, i) {
                return '<button type="button" class="acad-picker__opt" data-i="' + i + '">' +
                    '<span class="acad-picker__name">' + pickerEsc(primary(it)) + '</span>' +
                    '<span class="acad-picker__email">' + pickerEsc(secondary(it)) + '</span></button>';
            }).join('');
            menu.hidden = false;
            activeIdx = -1;
        }

        function choose(it) {
            selected = it;
            input.hidden = true;
            closeMenu();
            chip.hidden = false;
            chip.innerHTML = '<span class="acad-picker__chiptext"><strong>' + pickerEsc(primary(it)) + '</strong>' +
                '<span class="acad-picker__email">' + pickerEsc(secondary(it)) + '</span></span>' +
                '<button type="button" class="acad-picker__clear" aria-label="Clear">&times;</button>';
            if (opts.onChange) { opts.onChange(it); }
        }

        function clear() {
            selected = null;
            chip.hidden = true;
            chip.innerHTML = '';
            input.hidden = false;
            input.value = '';
            closeMenu();
            input.focus();
            if (opts.onChange) { opts.onChange(null); }
        }

        function runSearch() {
            var q = input.value.trim();
            if (q.length < 2) { showMessage(labels.hint); return; }
            showMessage(labels.searching);
            var mine = ++seq;
            Promise.resolve(opts.search(q)).then(function (list) {
                if (mine !== seq) { return; } // a newer keystroke superseded this response
                renderMenu(list);
            }).catch(function () { if (mine === seq) { closeMenu(); } });
        }

        function setActive(idx) {
            var opts2 = menu.querySelectorAll('.acad-picker__opt');
            if (!opts2.length) { return; }
            activeIdx = (idx + opts2.length) % opts2.length;
            opts2.forEach(function (o, i) { o.classList.toggle('is-active', i === activeIdx); });
            opts2[activeIdx].scrollIntoView({ block: 'nearest' });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(runSearch, 250);
        });
        input.addEventListener('keydown', function (ev) {
            if (menu.hidden) { return; }
            if (ev.key === 'ArrowDown') { ev.preventDefault(); setActive(activeIdx + 1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); setActive(activeIdx - 1); }
            else if (ev.key === 'Enter') {
                if (activeIdx >= 0 && items[activeIdx]) { ev.preventDefault(); choose(items[activeIdx]); }
            } else if (ev.key === 'Escape') { closeMenu(); }
        });
        menu.addEventListener('click', function (ev) {
            var b = ev.target.closest('.acad-picker__opt');
            if (b) { choose(items[parseInt(b.getAttribute('data-i'), 10)]); }
        });
        chip.addEventListener('click', function (ev) {
            if (ev.target.closest('.acad-picker__clear')) { clear(); }
        });
        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) { closeMenu(); }
        });

        return {
            value: function () { return selected ? String(idOf(selected)) : ''; },
            item: function () { return selected; },
            user: function () { return selected; }, // backwards-compatible alias
            clear: clear
        };
    }

    // Thin wrapper: pick a user (primary = full name, secondary = email).
    function userPicker(opts) {
        return picker(Object.assign({}, opts, {
            primary: function (u) { return u.fullname; },
            secondary: function (u) { return u.email || ''; }
        }));
    }

    w.AcademyUI.picker = picker;
    w.AcademyUI.userPicker = userPicker;
})(window);
