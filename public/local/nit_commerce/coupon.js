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
 * Copy-to-clipboard for the coupon code on the public coupon page
 * (local/nit_commerce/coupon.php).
 *
 * The code stays selectable text whether or not this runs — the button only saves the visitor
 * a drag — so nothing here is load-bearing and a clipboard the browser refuses is not an error
 * worth reporting.
 *
 * @module     local_nit_commerce/coupon
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var root = document.querySelector('.nitcpn');
        var btn = root && root.querySelector('[data-nitcpn-copy]');
        if (!root || !btn) {
            return;
        }

        var label = btn.querySelector('.nitcpn__codetext');
        var copied = root.getAttribute('data-nitcpn-copied') || '';
        var original = label ? label.textContent : '';
        var timer = null;

        btn.addEventListener('click', function() {
            var code = btn.getAttribute('data-nitcpn-copy') || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).catch(function() {
                    // A browser that refuses the clipboard still shows the code on screen.
                });
            }

            btn.classList.add('is-copied');
            if (label && copied) {
                label.textContent = copied;
            }

            window.clearTimeout(timer);
            timer = window.setTimeout(function() {
                btn.classList.remove('is-copied');
                if (label) {
                    label.textContent = original;
                }
            }, 1400);
        });
    });
})();
