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
 * Catalogue filter behaviour.
 *
 * The page is a plain GET form and works without any of this: every tick can be applied
 * with the "Apply filters" button, and every result already has its own address. What this
 * file adds is not having to press the button, folded option lists, and a filter panel that
 * does not jump back to the top on every reload.
 *
 * @module     local_nit_category/catalogue
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    // Long enough that typing "1500" is one submit rather than four.
    var TYPING_DELAY = 700;
    var SCROLL_KEY = 'nitcat:sidescroll';

    /**
     * Submit the form, leaving empty fields out of the address so a shared link carries
     * only the filters that are actually set.
     *
     * @param {HTMLFormElement} form
     */
    function submit(form) {
        Array.prototype.forEach.call(form.querySelectorAll('input[type="search"], input[type="number"]'),
            function (input) {
                if (input.value.trim() === '') {
                    input.disabled = true;
                }
            });
        // The page number belongs to the old result set, so it is never carried over.
        var page = form.querySelector('input[name="page"]');
        if (page) {
            page.remove();
        }
        form.submit();
    }

    /**
     * Remember where the filter panel was scrolled to, so applying a filter does not send
     * the reader back to the top of a long list of options.
     *
     * @param {Element} side
     */
    function keepScroll(side) {
        try {
            var saved = window.sessionStorage.getItem(SCROLL_KEY);
            if (saved) {
                side.scrollTop = parseInt(saved, 10) || 0;
            }
            window.addEventListener('beforeunload', function () {
                try {
                    window.sessionStorage.setItem(SCROLL_KEY, String(side.scrollTop));
                } catch (e) {
                    // Storage unavailable: the panel just starts at the top.
                }
            });
        } catch (e) {
            // Storage unavailable: nothing to restore.
        }
    }

    function init() {
        var form = document.querySelector('[data-nitcat-form]');
        if (!form) {
            return;
        }

        // With scripting on, the button is redundant — but it is only removed once we know
        // the change handlers below are actually wired up.
        var apply = form.querySelector('[data-nitcat-apply]');
        if (apply) {
            apply.hidden = true;
        }

        form.addEventListener('change', function (ev) {
            var target = ev.target;
            if (!target.name) {
                return;
            }
            if (target.type === 'checkbox' || target.tagName === 'SELECT') {
                submit(form);
            }
        });

        var timer = null;
        form.addEventListener('input', function (ev) {
            var target = ev.target;
            if (target.type !== 'number' && target.type !== 'search') {
                return;
            }
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                submit(form);
            }, TYPING_DELAY);
        });

        // Enter in the search box should search now, not after the typing pause.
        form.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && ev.target.type === 'search') {
                ev.preventDefault();
                window.clearTimeout(timer);
                submit(form);
            }
        });

        Array.prototype.forEach.call(form.querySelectorAll('[data-nitcat-more]'), function (button) {
            var group = button.closest('[data-nitcat-group]');
            var more = button.textContent;
            var less = button.getAttribute('data-less');
            button.addEventListener('click', function () {
                var open = group.classList.toggle('is-open');
                button.textContent = open ? less : more;
            });
        });

        var side = form.querySelector('.nitcat__side');
        if (side) {
            keepScroll(side);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
