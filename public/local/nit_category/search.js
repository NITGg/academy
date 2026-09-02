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
 * The header search box (SRS 4.22).
 *
 * The control works with none of this: it is a plain GET form pointing at search.php, so
 * pressing Enter gives the full results page, grouped and counted, with its own address.
 * What this file adds is the preview panel — the same groups, from the same endpoint,
 * shown while you type — plus the keyboard handling a combobox owes its user.
 *
 * It also closes the one hole in AC-4.22.4: a learner who reads "Nothing found" in the
 * panel has no reason to press Enter, so the miss would never reach the report. When the
 * panel comes back empty and the typing then stops, the term is reported to search.php's
 * logmiss endpoint — once per term, after the pause, so the report records what somebody
 * looked for rather than every prefix they typed on the way there.
 *
 * @module     local_nit_category/search
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function () {
    'use strict';

    // Long enough that a word is one request rather than seven.
    var TYPING_DELAY = 320;
    // How long the typing must have stopped before an empty panel counts as a real miss.
    var MISS_DELAY = 1500;
    // Matches site_search::MIN_LENGTH — below this the server answers with the hint only.
    var MIN_LENGTH = 2;

    /**
     * Wire up one search control.
     *
     * @param {HTMLElement} root the wrapper carrying data-nitsearch
     */
    function init(root) {
        var form = root.matches('form') ? root : root.querySelector('form');
        var input = root.querySelector('[data-nitsearch-input]');
        var panel = root.querySelector('[data-nitsearch-panel]');
        var toggle = document.querySelector('[data-nitsearch-toggle]');

        if (!form || !input || !panel) {
            return;
        }

        var endpoint = form.getAttribute('action');
        var sesskey = (window.M && M.cfg && M.cfg.sesskey) || '';
        var typingTimer = null;
        var missTimer = null;
        var controller = null;
        var lastQuery = null;
        var loggedMiss = null;

        /**
         * Show or hide the panel, keeping the combobox state honest for screen readers.
         *
         * @param {boolean} open
         */
        function setOpen(open) {
            root.classList.toggle('is-open', open);
            panel.hidden = !open;
            input.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        /**
         * Drop everything in flight — used when the box empties or closes.
         */
        function cancel() {
            window.clearTimeout(typingTimer);
            window.clearTimeout(missTimer);
            if (controller) {
                controller.abort();
                controller = null;
            }
        }

        /**
         * Tell the server that this term found nothing, so it reaches the report.
         *
         * @param {string} query
         */
        function reportMiss(query) {
            if (!sesskey || loggedMiss === query) {
                return;
            }
            loggedMiss = query;
            var url = endpoint + '?action=logmiss&sesskey=' + encodeURIComponent(sesskey) +
                '&q=' + encodeURIComponent(query);
            // Fire and forget: the panel has already told the visitor what it found, and a
            // failure here must never surface as an error on a page they are still typing on.
            window.fetch(url, {credentials: 'same-origin'}).catch(function () {
                loggedMiss = null;
            });
        }

        /**
         * Fetch and draw the panel for what is currently typed.
         */
        function refresh() {
            var query = input.value.trim();

            if (query.length < MIN_LENGTH) {
                cancel();
                setOpen(false);
                lastQuery = null;
                return;
            }
            if (query === lastQuery) {
                setOpen(true);
                return;
            }

            if (controller) {
                controller.abort();
            }
            controller = window.AbortController ? new window.AbortController() : null;

            var url = endpoint + '?fragment=1&q=' + encodeURIComponent(query);
            window.fetch(url, {
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }).then(function (response) {
                return response.ok ? response.text() : null;
            }).then(function (html) {
                if (html === null || input.value.trim() !== query) {
                    return;
                }
                lastQuery = query;
                panel.innerHTML = html;
                setOpen(true);

                // AC-4.22.4: an empty panel is a miss, but only once the typing settles —
                // "mar", "mark" and "market" on the way to "marketing" are not three
                // separate things anybody looked for.
                var result = panel.querySelector('[data-nitsearch-total]');
                var total = result ? parseInt(result.getAttribute('data-nitsearch-total'), 10) : -1;
                window.clearTimeout(missTimer);
                if (total === 0) {
                    missTimer = window.setTimeout(function () {
                        if (input.value.trim() === query) {
                            reportMiss(query);
                        }
                    }, MISS_DELAY);
                }
            }).catch(function () {
                // An aborted request is the normal case here (the next keystroke), and a
                // failed one leaves the form working as a plain GET. Neither is worth a
                // message on top of the page.
            });
        }

        input.setAttribute('autocomplete', 'off');
        input.setAttribute('aria-expanded', 'false');
        panel.hidden = true;

        input.addEventListener('input', function () {
            window.clearTimeout(typingTimer);
            window.clearTimeout(missTimer);
            typingTimer = window.setTimeout(refresh, TYPING_DELAY);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= MIN_LENGTH) {
                refresh();
            }
        });

        // Arrow keys walk the results; Enter on a highlighted row opens it, and Enter
        // anywhere else submits the form, which is what the control does without any script.
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                cancel();
                setOpen(false);
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                return;
            }
            var rows = panel.querySelectorAll('.nitsearch-row, .nitsearch__all');
            if (!rows.length) {
                return;
            }
            event.preventDefault();
            setOpen(true);
            rows[event.key === 'ArrowDown' ? 0 : rows.length - 1].focus();
        });

        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
                input.focus();
                return;
            }
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                return;
            }
            var rows = Array.prototype.slice.call(
                panel.querySelectorAll('.nitsearch-row, .nitsearch__all'));
            var at = rows.indexOf(document.activeElement);
            if (at < 0) {
                return;
            }
            event.preventDefault();
            var next = at + (event.key === 'ArrowDown' ? 1 : -1);
            if (next < 0) {
                input.focus();
            } else if (next < rows.length) {
                rows[next].focus();
            }
        });

        // Clicking away closes the panel; clicking inside it must not, or the link under
        // the pointer would never receive the click.
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target) && (!toggle || !toggle.contains(event.target))) {
                setOpen(false);
            }
        });

        // On a narrow screen the box is folded behind a magnifier: the same form, revealed.
        if (toggle) {
            toggle.addEventListener('click', function (event) {
                event.preventDefault();
                var open = root.classList.toggle('is-expanded');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open) {
                    input.focus();
                } else {
                    setOpen(false);
                }
            });
        }
    }

    /**
     * Wire up every control on the page (there is one, in the header).
     */
    function start() {
        var roots = document.querySelectorAll('[data-nitsearch]');
        Array.prototype.forEach.call(roots, init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
