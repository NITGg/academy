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
 * Behaviour for the NIT offers bar: cycling between offers and dismissing the bar.
 *
 * Everything here is an enhancement. With scripting off the bar still shows its first
 * offer, still links out, and simply does not rotate or close — which is why the first
 * row is marked active server-side rather than by this file.
 *
 * @module     block_nit_offers/bar
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    var INTERVAL = 6500;
    // One key for the whole site: it stores WHICH set of offers was dismissed, so a new
    // or changed offer produces a new fingerprint and the bar comes back on its own.
    var STORAGE_KEY = 'nitoff:dismissed';

    /**
     * Read the dismissed fingerprint. Storage can throw outright (private windows,
     * blocked site data), so a failure means "nothing was dismissed".
     *
     * @return {string}
     */
    function dismissedFingerprint() {
        try {
            return window.localStorage.getItem(STORAGE_KEY) || '';
        } catch (e) {
            return '';
        }
    }

    /**
     * Remember that this offer set was dismissed. Silently a no-op when storage is
     * unavailable — the bar then simply returns on the next page load.
     *
     * @param {string} fingerprint
     */
    function rememberDismissal(fingerprint) {
        try {
            window.localStorage.setItem(STORAGE_KEY, fingerprint);
        } catch (e) {
            // Nothing to do: dismissal just does not persist.
        }
    }

    /**
     * Wire up one bar.
     *
     * @param {Element} bar
     */
    function init(bar) {
        if (bar.getAttribute('data-nitoff-ready')) {
            return;
        }
        bar.setAttribute('data-nitoff-ready', '1');

        var fingerprint = bar.getAttribute('data-fingerprint') || '';
        var closeBtn = bar.querySelector('[data-nitoff-close]');

        if (closeBtn && fingerprint && dismissedFingerprint() === fingerprint) {
            bar.remove();
            return;
        }

        var items = Array.prototype.slice.call(bar.querySelectorAll('[data-nitoff-item]'));
        var dots = Array.prototype.slice.call(bar.querySelectorAll('[data-nitoff-dot]'));
        var current = 0;
        var timer = null;

        function show(index) {
            current = (index + items.length) % items.length;
            items.forEach(function (item, i) {
                item.classList.toggle('is-active', i === current);
            });
            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === current);
                dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
            });
        }

        function stop() {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function start() {
            stop();
            timer = window.setInterval(function () {
                show(current + 1);
            }, INTERVAL);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                stop();
                if (fingerprint) {
                    rememberDismissal(fingerprint);
                }
                bar.remove();
            });
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () {
                // A deliberate choice wins over the timer: stop advancing on its own once
                // the reader has picked an offer to look at.
                stop();
                show(i);
            });
        });

        // Auto-advance only when there is somewhere to advance to, the block asked for it,
        // and the visitor has not asked for reduced motion.
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (items.length > 1 && bar.getAttribute('data-rotate') && !reduced) {
            start();
            bar.addEventListener('mouseenter', stop);
            bar.addEventListener('mouseleave', start);
            bar.addEventListener('focusin', stop);
            bar.addEventListener('focusout', start);
        }
    }

    function initAll() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-nitoff]'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
}());
