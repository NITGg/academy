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
 * The live countdown on the "Resend email" action.
 *
 * AC-4.2.2: "The Resend action is disabled for 60 seconds after each send and
 * displays a live countdown."
 *
 * The countdown is cosmetic. The wait is enforced in verification.php, which
 * refuses an early request whatever this module says, so a user with JavaScript
 * off simply sees a link that declines until the wait is over.
 *
 * @module     local_profilefields/resendcountdown
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Count one anchor down to zero, then let it work.
 *
 * @param {HTMLElement} link The resend anchor, carrying data-wait and data-label.
 * @param {String} waitLabel Template holding the literal {seconds}.
 * @return {void}
 */
const countdown = (link, waitLabel) => {
    let left = parseInt(link.dataset.wait || '0', 10);
    const ready = link.dataset.label || link.textContent;

    const release = () => {
        link.classList.remove('disabled');
        link.setAttribute('aria-disabled', 'false');
        link.textContent = ready;
    };

    if (!(left > 0)) {
        release();
        return;
    }

    const paint = () => {
        link.textContent = waitLabel.replace('{seconds}', String(left));
    };

    paint();

    const tick = setInterval(() => {
        left -= 1;
        if (left <= 0) {
            clearInterval(tick);
            release();
            return;
        }
        paint();
    }, 1000);

    // A disabled anchor is still clickable in every browser, so the click has to
    // be refused as well as the styling applied.
    link.addEventListener('click', (e) => {
        if (link.classList.contains('disabled')) {
            e.preventDefault();
        }
    });
};

/**
 * Start the countdown on every resend action on the page.
 *
 * @param {Object} config
 * @param {String} config.waitLabel Localised template containing {seconds}.
 * @return {void}
 */
export const init = (config = {}) => {
    const waitLabel = config.waitLabel || '{seconds}';

    document.querySelectorAll('[data-nit-resend]').forEach((link) => countdown(link, waitLabel));
};
