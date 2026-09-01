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
 * Live filtering for the categories page (AC-4.8.1).
 *
 * The page underneath is a plain GET form and is completely usable without any of this:
 * every tick can be applied with the "Apply filters" button and every result already has
 * its own address. What this adds is that the grid and the count change where they stand
 * instead of the page being thrown away and rebuilt.
 *
 * It does that by asking the server for the same page again with `fragment=1`, which
 * returns just the part that depends on the filters, rendered by the very same code that
 * rendered it the first time. Nothing about how a filter works is duplicated here — this
 * file knows how to read a form, swap some HTML and keep the address bar honest, and
 * nothing else. If the request fails it falls back to submitting the form for real, so a
 * flaky connection costs a reload rather than a broken page.
 *
 * @module     local_nit_category/categories
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    // Long enough that typing "trade" is one request rather than five.
    var TYPING_DELAY = 350;

    var form = null;
    var region = null;
    var search = null;
    var base = '';
    var controller = null;
    var timer = null;

    /**
     * The current state of every control, empty fields left out so a shared address carries
     * only the filters that are really set.
     *
     * @return {URLSearchParams}
     */
    function formParams() {
        var params = new URLSearchParams();
        new FormData(form).forEach(function (value, key) {
            if (String(value).trim() !== '') {
                params.append(key, value);
            }
        });
        return params;
    }

    /**
     * @param {URLSearchParams} params
     * @return {string}
     */
    function urlFor(params) {
        var query = params.toString();
        return base + (query ? '?' + query : '');
    }

    /**
     * Give up on the fetch and let the browser do it the old way.
     *
     * Empty fields are disabled first for the same reason they are dropped above: they
     * would otherwise litter the address with `pricemin=`.
     */
    function fallback() {
        Array.prototype.forEach.call(form.querySelectorAll('input[type="search"], input[type="number"]'),
            function (input) {
                if (input.value.trim() === '') {
                    input.disabled = true;
                }
            });
        form.submit();
    }

    /**
     * Where the caret is, so it can be put back after the panel is replaced.
     *
     * Without this, ticking a box moves focus to the top of the document and a
     * keyboard-only reader loses their place in the filter list on every single tick.
     *
     * @return {?Object}
     */
    function focusMark() {
        var el = document.activeElement;
        if (!el || !el.name || !region.contains(el)) {
            return null;
        }
        return {
            name: el.name,
            value: el.value,
            start: el.selectionStart === undefined ? null : el.selectionStart
        };
    }

    /**
     * @param {?Object} mark from focusMark()
     */
    function focusRestore(mark) {
        if (!mark) {
            return;
        }
        var selector = '[name="' + (window.CSS && CSS.escape ? CSS.escape(mark.name) : mark.name) + '"]';
        var candidates = region.querySelectorAll(selector);
        var target = null;
        Array.prototype.forEach.call(candidates, function (el) {
            if (!target && el.value === mark.value) {
                target = el;
            }
        });
        target = target || candidates[0];
        if (!target) {
            return;
        }
        target.focus();
        if (mark.start !== null && target.setSelectionRange) {
            try {
                target.setSelectionRange(mark.start, mark.start);
            } catch (e) {
                // Not a control that has a caret; focus alone is enough.
            }
        }
    }

    /**
     * Wire the controls that live inside the swapped region.
     *
     * Called once at start-up and again after every swap, because the elements it touches
     * are new objects each time.
     */
    function wire() {
        // Redundant with scripting on — but only hidden once the handlers replacing it are
        // known to be attached.
        var apply = region.querySelector('[data-nitcat-apply]');
        if (apply) {
            apply.hidden = true;
        }

        Array.prototype.forEach.call(region.querySelectorAll('[data-nitcat-more]'), function (button) {
            var group = button.closest('[data-nitcat-group]');
            var more = button.textContent;
            var less = button.getAttribute('data-less');
            button.addEventListener('click', function () {
                var open = group.classList.toggle('is-open');
                button.textContent = open ? less : more;
            });
        });
    }

    /**
     * Fetch the results region for these parameters and put it on the page.
     *
     * @param {URLSearchParams} params
     * @param {boolean} push add a history entry, rather than rewriting the current one
     */
    function load(params, push) {
        var address = urlFor(params);

        // A stale answer arriving after a newer one would show the wrong grid, so the
        // previous request is cancelled rather than merely ignored.
        if (controller) {
            controller.abort();
        }
        controller = window.AbortController ? new AbortController() : null;

        var wanted = new URLSearchParams(params.toString());
        wanted.set('fragment', '1');

        region.setAttribute('aria-busy', 'true');

        window.fetch(urlFor(wanted), {
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('fragment ' + response.status);
            }
            return response.text();
        }).then(function (html) {
            var mark = focusMark();
            region.innerHTML = html;
            region.removeAttribute('aria-busy');
            wire();
            focusRestore(mark);
            if (push) {
                window.history.pushState({nitcats: 1}, '', address);
            } else {
                window.history.replaceState({nitcats: 1}, '', address);
            }
        }).catch(function (error) {
            if (error && error.name === 'AbortError') {
                return;
            }
            region.removeAttribute('aria-busy');
            fallback();
        });
    }

    /**
     * Re-render from an address rather than from the form — what the Back button and the
     * active-filter chips both need.
     *
     * @param {string} address
     */
    function loadAddress(address) {
        var query = address.indexOf('?') === -1 ? '' : address.slice(address.indexOf('?') + 1);
        var params = new URLSearchParams(query);
        // The search box sits outside the swapped region, so nothing else will update it.
        search.value = params.get('q') || '';
        load(params, false);
    }

    function init() {
        form = document.querySelector('[data-nitcats-form]');
        region = form && form.querySelector('[data-nitcats-region]');
        search = form && form.querySelector('input[type="search"]');

        // No fetch, no URLSearchParams, no page: the form still works on its own.
        if (!form || !region || !window.fetch || !window.URLSearchParams) {
            return;
        }

        base = form.getAttribute('action') || window.location.pathname;

        wire();

        // A tick or a sort change is a deliberate step, so it earns a history entry.
        form.addEventListener('change', function (ev) {
            var target = ev.target;
            if (!target.name) {
                return;
            }
            if (target.type === 'checkbox' || target.tagName === 'SELECT') {
                window.clearTimeout(timer);
                load(formParams(), true);
            }
        });

        // Typing is not: rewriting the current entry keeps Back from walking back through
        // every keystroke.
        form.addEventListener('input', function (ev) {
            var target = ev.target;
            if (target.type !== 'number' && target.type !== 'search') {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                load(formParams(), false);
            }, TYPING_DELAY);
        });

        // Enter in the search box searches now rather than after the pause — and must not
        // submit the form, which would be the full reload this whole file exists to avoid.
        form.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && ev.target.type === 'search') {
                ev.preventDefault();
                window.clearTimeout(timer);
                load(formParams(), true);
            }
        });

        form.addEventListener('submit', function (ev) {
            // Only reachable from the search button, since Apply is hidden. Same treatment.
            if (ev.submitter && ev.submitter.hasAttribute('data-nitcat-apply')) {
                return;
            }
            ev.preventDefault();
            window.clearTimeout(timer);
            load(formParams(), true);
        });

        // The active-filter chips, "Clear all" and the empty state's suggestions are all
        // ordinary links back to this page. They keep working without script; with script
        // they go through the same re-render as everything else.
        region.addEventListener('click', function (ev) {
            var link = ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!link || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button) {
                return;
            }
            var href = link.getAttribute('href') || '';
            if (href.split('?')[0] !== base) {
                return;     // A category card, off to its own page.
            }
            ev.preventDefault();
            window.clearTimeout(timer);
            search.value = new URLSearchParams(href.split('?')[1] || '').get('q') || '';
            load(new URLSearchParams(href.split('?')[1] || ''), true);
        });

        window.addEventListener('popstate', function (ev) {
            if (!ev.state || !ev.state.nitcats) {
                return;     // Not one of ours: let the browser leave the page.
            }
            loadAddress(window.location.pathname + window.location.search);
        });

        // So the first Back from a filtered state returns here rather than leaving.
        window.history.replaceState({nitcats: 1}, '',
            window.location.pathname + window.location.search);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
