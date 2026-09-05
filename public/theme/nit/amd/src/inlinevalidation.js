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
 * Tell the user which field is wrong, as they leave it.
 *
 * US-4.1.3: "As a guest, I want to be told immediately and specifically which
 * field is wrong, so that I can correct it without guessing."
 *
 * The server already produces every message AC-4.1.15 words, but only on submit -
 * the page reloads and the errors appear together at the top of a form the user
 * has to re-read. "Immediately" means beside the field, while it is still the
 * field they are thinking about.
 *
 * Two rules keep this honest:
 *
 * 1. **The server stays the authority.** Every message here is the same string
 *    the server would produce, handed in from PHP rather than written twice - see
 *    theme_nit\local\hook_callbacks::load_inline_validation(). Anything this
 *    module cannot judge (is the address already registered? does the country
 *    match the IP?) is deliberately left to submit, because guessing client-side
 *    would mean showing an answer the server might contradict.
 *
 * 2. **It never speaks first.** A field is only validated once the user has left
 *    it, and an untouched field is silent. Telling somebody their name is too
 *    short before they have finished typing it is worse than saying nothing.
 *
 * @module     theme_nit/inlinevalidation
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {String} Marks a form already wired. */
const DONE = 'nit-inline-validated';

/** @type {Number} Shortest accepted first or last name (AC-4.1.15). */
const NAME_MIN = 2;

/** @type {Number} Longest accepted first or last name (AC-4.1.15). */
const NAME_MAX = 50;

/** @type {Number} Shortest accepted password (AC-4.1.6). */
const PASSWORD_MIN = 8;

/**
 * The rule set, mirroring local_profilefields\validation.
 *
 * Each entry returns the key of the message to show, or null when the value is
 * acceptable. The order inside each function is the specification's order, so the
 * message named is the same one the server would name.
 */
const RULES = {
    name: (v) => {
        const value = v.trim();
        if (value === '') {
            return 'empty';
        }
        if ([...value].length < NAME_MIN || [...value].length > NAME_MAX) {
            return 'errnamelength';
        }
        // Letters (any script), marks, spaces, hyphens and both apostrophes.
        if (!/^[\p{L}\p{M} '’-]+$/u.test(value)) {
            return 'errnamechars';
        }
        return null;
    },

    email: (v) => {
        const value = v.trim();
        if (value === '') {
            return 'erremailempty';
        }
        // Deliberately loose. The server decides; this only catches the shapes a
        // person can see are wrong, and a stricter pattern here would reject
        // addresses the server accepts.
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            return 'erremailformat';
        }
        return null;
    },

    password: (v) => {
        if ([...v].length < PASSWORD_MIN) {
            return 'pwtooshort';
        }
        if (!/\p{Lu}/u.test(v)) {
            return 'pwnoupper';
        }
        if (!/\p{Ll}/u.test(v)) {
            return 'pwnolower';
        }
        if (!/[0-9]/.test(v)) {
            return 'pwnodigit';
        }
        return null;
    },

    phone: (v) => {
        const value = v.trim();
        if (value === '') {
            return 'errphoneempty';
        }
        if (/[^0-9 ()-]/.test(value)) {
            return 'errphonedigits';
        }
        return null;
    },
};

/**
 * Which rule applies to a control, and which message its "empty" case uses.
 *
 * Keyed on the field's name, because that is what both sides agree on: PHP names
 * the same fields in validation::signup_fields().
 *
 * @param {HTMLElement} el
 * @return {{rule: Function, emptyKey: String}|null}
 */
const ruleFor = (el) => {
    const name = el.name || '';

    if (name === 'firstname') {
        return {rule: RULES.name, emptyKey: 'errfirstnameempty'};
    }
    if (name === 'lastname') {
        return {rule: RULES.name, emptyKey: 'errlastnameempty'};
    }
    if (name === 'email') {
        return {rule: RULES.email, emptyKey: 'erremailempty'};
    }
    if (name === 'password' || name === 'newpassword1' || name === 'newpassword') {
        return {rule: RULES.password, emptyKey: 'pwtooshort'};
    }
    if (/\[number\]$/.test(name)) {
        return {rule: RULES.phone, emptyKey: 'errphoneempty'};
    }

    return null;
};

/**
 * Show or clear the message under one control.
 *
 * Moodle already renders an error slot per element (`#id_error_<name>`), styled
 * and wired to `aria-describedby`. Writing into it rather than inventing our own
 * means the message looks and behaves exactly like a server-side one - including
 * for a screen reader.
 *
 * @param {HTMLElement} el the control
 * @param {String} message the message, or '' to clear
 * @return {void}
 */
const setMessage = (el, message) => {
    const item = el.closest('.fitem');
    if (!item) {
        return;
    }

    // A control inside an mform *group* has two slots to choose from, and the
    // narrow one is the wrong one. The phone is a group: its country select and
    // its number box are two form items sharing one row, so the number box's own
    // slot is only as wide as its half of that row - 140px on a phone, where
    // "Please enter your phone number." wrapped into two cramped lines under one
    // box while the space beside it sat empty.
    //
    // The group has a full-width slot of its own underneath, and it is the slot
    // PHP writes to as well: profile_field_phone pins every error to the group's
    // name, never to a half of it. So preferring it here is not only wider, it is
    // what stops the message moving between before-submit and after-submit.
    const group = item.parentElement ? item.parentElement.closest('.fitem') : null;
    const groupslot = group ? group.querySelector('[id^="fgroup_id_error"]') : null;

    const owner = groupslot ? group : item;
    const slot = groupslot || item.querySelector('.form-control-feedback, [id^="id_error"]');
    if (!slot) {
        return;
    }

    if (message) {
        slot.textContent = message;
        slot.style.display = 'block';
        owner.classList.add('has-danger');
        el.classList.add('is-invalid');
        el.setAttribute('aria-invalid', 'true');
    } else {
        slot.textContent = '';
        slot.style.display = '';
        owner.classList.remove('has-danger');
        el.classList.remove('is-invalid');
        el.removeAttribute('aria-invalid');
    }
};

/**
 * Wire one form.
 *
 * @param {HTMLFormElement} form
 * @param {Object} strings message key => localised sentence
 * @return {void}
 */
const wire = (form, strings) => {
    const touched = new WeakSet();

    const check = (el) => {
        const found = ruleFor(el);
        if (!found) {
            return;
        }

        const key = found.rule(String(el.value ?? ''));
        const resolved = key === 'empty' ? found.emptyKey : key;

        setMessage(el, resolved ? (strings[resolved] || '') : '');
    };

    // Validate on the way out of a field, not on every keystroke.
    form.addEventListener('focusout', (e) => {
        const el = e.target;
        if (!el || !ruleFor(el)) {
            return;
        }
        touched.add(el);
        check(el);
    });

    // Once a field has been judged, correcting it should clear the message as the
    // user types - otherwise the error sits there until they leave again, which
    // reads as "still wrong" while they are fixing it.
    form.addEventListener('input', (e) => {
        const el = e.target;
        if (!el || !touched.has(el)) {
            return;
        }
        check(el);
    });
};

/**
 * Validate the opted-in forms on this page as the user moves through them.
 *
 * @param {Object} config
 * @param {String[]} [config.forms] Selectors for the forms to wire.
 * @param {Object} [config.strings] Message key => localised sentence.
 * @return {void}
 */
export const init = (config = {}) => {
    const strings = config.strings || {};

    (config.forms || ['form[data-nit-gate]']).forEach((selector) => {
        let forms;
        try {
            forms = document.querySelectorAll(selector);
        } catch (e) {
            return;
        }

        forms.forEach((form) => {
            if (form.tagName !== 'FORM' || form.classList.contains(DONE)) {
                return;
            }
            form.classList.add(DONE);
            wire(form, strings);
        });
    });
};
