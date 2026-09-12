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
 * Say *why* one of two either/or fields is locked, and offer the way out.
 *
 * Core's forgotten-password form disables the e-mail box the moment the
 * username box has a value, and vice versa (`disabledIf` in
 * login/forgot_password_form.php). Core updates that state on blur, so the
 * click that leaves the username box is the same click that lands on a box
 * that has just stopped accepting input - and nothing on the page says so.
 * On the dark brand the disabled state is barely a shade darker, so the
 * learner reads it as "the site will not let me type" rather than "one or
 * the other".
 *
 * This module does not change the rule - the server still refuses a form
 * with both filled. It watches the `disabled` attribute core toggles and,
 * while a box is locked, prints under it which field is holding it and a
 * button that clears that field and hands focus back. The row also gets a
 * class the stylesheet dims, so the state is visible before it is read.
 *
 * Authored as a native ES module - no jQuery. Built to amd/build.
 *
 * @module     theme_nit/eitheror
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {String} Marker class so a double init() never wires the same field twice. */
const DONE = 'nit-eitheror-enhanced';

/** @type {String} Put on the form row while its box is locked; the stylesheet dims it. */
const LOCKED = 'nit-eitheror-locked';

/**
 * Find the form row (the `.fitem` mform wrapper) a field lives in.
 *
 * @param {HTMLInputElement} input
 * @return {HTMLElement}
 */
const rowOf = (input) => input.closest('.fitem') || input.parentElement;

/**
 * Build the hint that sits under a locked box.
 *
 * @param {String} text Why the box is locked.
 * @param {String} clearLabel Label of the button that unlocks it.
 * @return {{hint: HTMLElement, button: HTMLButtonElement}}
 */
const buildHint = (text, clearLabel) => {
    const hint = document.createElement('div');
    hint.className = 'nit-eitheror-hint form-text';
    hint.setAttribute('role', 'status');
    hint.hidden = true;

    const message = document.createElement('span');
    message.className = 'nit-eitheror-text';
    message.textContent = text;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-link p-0 align-baseline nit-eitheror-clear';
    button.textContent = clearLabel;

    hint.append(message, ' ', button);
    return {hint, button};
};

/**
 * Wire one box against the box that can lock it.
 *
 * @param {HTMLInputElement} input The box that gets locked.
 * @param {HTMLInputElement} other The box whose value locks it.
 * @param {Object} strings `locked` (why) and `clear` (the button).
 */
const wire = (input, other, strings) => {
    if (input.classList.contains(DONE)) {
        return;
    }
    input.classList.add(DONE);

    const row = rowOf(input);
    const {hint, button} = buildHint(strings.locked, strings.clear);

    // After core's own feedback slot when there is one, so a server-side
    // error and this hint never fight for the same line.
    const feedback = row.querySelector('.form-control-feedback');
    if (feedback) {
        feedback.after(hint);
    } else {
        input.after(hint);
    }

    const render = () => {
        const locked = input.disabled;
        hint.hidden = !locked;
        row.classList.toggle(LOCKED, locked);
    };

    button.addEventListener('click', () => {
        other.value = '';
        // Core's dependency manager listens for change (lib/form/form.js), so
        // this is what actually lifts the lock. `input` keeps any other
        // listener (the button gate, inline validation) in step too.
        other.dispatchEvent(new Event('input', {bubbles: true}));
        other.dispatchEvent(new Event('change', {bubbles: true}));
        render();
        if (!input.disabled) {
            input.focus();
        }
    });

    // A disabled input swallows the click, so the row takes it: a press on the
    // locked box sends the eye - and the keyboard - to the way out.
    row.addEventListener('click', (e) => {
        if (input.disabled && !hint.contains(e.target)) {
            hint.classList.remove('nit-eitheror-nudge');
            // Restart the animation even when it is mid-flight.
            void hint.offsetWidth;
            hint.classList.add('nit-eitheror-nudge');
            button.focus();
        }
    });

    // Core flips `disabled` from its own YUI handlers on blur/change; watching
    // the attribute keeps this in step whichever event did it.
    new MutationObserver(render).observe(input, {attributes: true, attributeFilter: ['disabled']});
    render();
};

/**
 * Entry point.
 *
 * @param {Object} config
 * @param {String} config.form Selector of the form holding the pair.
 * @param {Array<Object>} config.pairs Each `{name, lockedBy, strings: {locked, clear}}`:
 *     the box called `name` is locked while the box called `lockedBy` has a value.
 */
export const init = (config) => {
    const form = document.querySelector(config.form);
    if (!form) {
        return;
    }

    (config.pairs || []).forEach((pair) => {
        const input = form.querySelector(`[name="${pair.name}"]`);
        const other = form.querySelector(`[name="${pair.lockedBy}"]`);
        if (input && other) {
            wire(input, other, pair.strings);
        }
    });
};
