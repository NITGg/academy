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
 * The price range slider, shared by the catalogue and the categories page.
 *
 * Two range inputs stacked on one track. They carry no name: the pair of number inputs
 * beside them is what the form submits, and this only writes into those when a handle is
 * actually away from its end — so an untouched slider adds nothing to the address and
 * "clear all" really does clear.
 *
 * Writing happens on `change` (the drag ending) rather than on `input` (every pixel of it),
 * and the change event it fires on the number input is an ordinary bubbling one, so each
 * page's existing filter handler picks it up without knowing this file exists. Labels and
 * the filled part of the track follow `input`, so dragging still feels live.
 *
 * @module     local_nit_category/pricerange
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    /**
     * "EGP 1,450" — grouped digits, no decimals on a whole number.
     *
     * @param {number} amount
     * @param {string} currency
     * @return {string}
     */
    function money(amount, currency) {
        var rounded = Math.round(amount * 100) / 100;
        var text;
        try {
            text = rounded.toLocaleString(document.documentElement.lang || 'en',
                {maximumFractionDigits: 2});
        } catch (e) {
            text = String(rounded);
        }
        return currency ? currency + ' ' + text : text;
    }

    /**
     * Wire one slider.
     *
     * @param {Element} box the [data-nitcat-price] wrapper
     */
    function wire(box) {
        if (box.dataset.nitcatWired) {
            return;
        }
        box.dataset.nitcatWired = '1';

        var low = box.querySelector('[data-nitcat-price-low]');
        var high = box.querySelector('[data-nitcat-price-high]');
        var fill = box.querySelector('[data-nitcat-price-fill]');
        var lowlabel = box.querySelector('[data-nitcat-price-lowlabel]');
        var highlabel = box.querySelector('[data-nitcat-price-highlabel]');
        var inputs = box.querySelectorAll('[data-nitcat-price-input]');

        if (!low || !high || inputs.length < 2) {
            return;
        }

        var min = parseFloat(box.dataset.min) || 0;
        var max = parseFloat(box.dataset.max) || 0;
        var currency = box.dataset.currency || '';
        var span = max - min;

        /**
         * Keep the handles from crossing, then repaint the track and the two end labels.
         */
        function paint() {
            var a = parseFloat(low.value);
            var b = parseFloat(high.value);

            // Whichever handle was moved gives way, so the pair can never invert.
            if (a > b) {
                if (document.activeElement === low) {
                    b = a;
                    high.value = String(b);
                } else {
                    a = b;
                    low.value = String(a);
                }
            }

            if (fill && span > 0) {
                fill.style.insetInlineStart = ((a - min) / span * 100) + '%';
                fill.style.width = ((b - a) / span * 100) + '%';
            }
            if (lowlabel) {
                lowlabel.textContent = money(a, currency);
            }
            if (highlabel) {
                highlabel.textContent = money(b, currency);
            }
        }

        /**
         * Copy the handles into the inputs the form submits — but only where the handle
         * has actually moved off its end. A handle sitting at the far end is not a filter,
         * it is the absence of one, and it must not appear in the address.
         */
        function commit() {
            var a = parseFloat(low.value);
            var b = parseFloat(high.value);

            inputs[0].value = a > min ? String(a) : '';
            inputs[1].value = b < max ? String(b) : '';

            // Bubbling, so the page's own change handler re-filters. Fired on one input
            // only: two events would mean two requests for one drag.
            inputs[0].dispatchEvent(new Event('change', {bubbles: true}));
        }

        low.addEventListener('input', paint);
        high.addEventListener('input', paint);
        low.addEventListener('change', commit);
        high.addEventListener('change', commit);

        paint();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-nitcat-price]'), wire);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // The categories page swaps its filter panel out on every change, so the slider that
    // comes back is a new element that has never been wired. Re-running init after each
    // swap is what keeps it working; a MutationObserver rather than a hook because this
    // file should not need to know which page it is on.
    if (window.MutationObserver) {
        new MutationObserver(function () {
            init();
        }).observe(document.documentElement, {childList: true, subtree: true});
    }
}());
