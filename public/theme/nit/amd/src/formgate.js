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
 * Keep a form's submit button disabled until its required fields are filled in.
 *
 * AC-4.1.1: "The Create Account button is disabled until every mandatory field
 * passes client-side validation and the Terms and Conditions checkbox is
 * ticked."
 *
 * Three rules govern this module, and all three exist to stop it becoming the
 * thing that traps a user in a form:
 *
 * 1. **Opt in, never opt out.** Only forms carrying `data-nit-gate` are touched.
 *    Moodle's administrative forms are full of conditional fields whose required
 *    marker is present while the field is hidden, and a gate there would leave
 *    an administrator with a dead button and nothing to read. The PHP side stamps
 *    the attribute on this academy's own screens only.
 *
 * 2. **Presence, not correctness.** The gate asks "has this been answered?", not
 *    "is this right?". Format, uniqueness and policy remain the server's job and
 *    still produce the specification's messages on submit. A gate that tried to
 *    reproduce them would disagree with the server sooner or later, and the user
 *    would lose that argument silently.
 *
 * 3. **Fail open.** Anything unexpected - a throw, a form whose fields cannot be
 *    read - releases the button. A form that cannot be submitted is a worse
 *    outcome than a form submitted with a blank box, because the second one comes
 *    back with an explanation.
 *
 * Authored as a native ES module - no jQuery. Built to amd/build.
 *
 * @module     theme_nit/formgate
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {String} Marks a form already wired, so a second init() is a no-op. */
const DONE = 'nit-gate-enhanced';

/** @type {String} Opt-in attribute. Only forms carrying this are gated. */
const OPT_IN = 'data-nit-gate';

/** @type {String} Put this on a field to keep it out of the gate. */
const IGNORE = 'data-nit-gate-ignore';

/**
 * The submit buttons belonging to a form.
 *
 * Cancel is deliberately excluded: a user who cannot fill the form in is exactly
 * the user who needs to leave it.
 *
 * @param {HTMLFormElement} form
 * @return {HTMLElement[]}
 */
const submitsOf = (form) => [...form.querySelectorAll('button[type="submit"], input[type="submit"]')]
    .filter((el) => !el.matches('[name="cancel"], .btn-cancel, [data-cancel]'));

/**
 * Is this control on screen and answerable?
 *
 * `offsetParent` is null for anything with `display: none` anywhere up the tree,
 * which is how Moodle hides a conditional field and how our own layout hides the
 * elements it replaces with hidden inputs. A field the user cannot see must not
 * be able to hold the button down.
 *
 * @param {HTMLElement} el
 * @return {Boolean}
 */
const isLive = (el) => !el.disabled
    && el.type !== 'hidden'
    && !el.hasAttribute(IGNORE)
    && (el.offsetParent !== null || el.type === 'radio' || el.type === 'checkbox');

/**
 * Does this control count as answered?
 *
 * A radio is answered when any member of its group is chosen, which is why the
 * form is consulted rather than the element alone.
 *
 * @param {HTMLElement} el
 * @param {HTMLFormElement} form
 * @return {Boolean}
 */
const isAnswered = (el, form) => {
    if (el.type === 'checkbox') {
        return el.checked;
    }
    if (el.type === 'radio') {
        return !!form.querySelector(`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`);
    }
    if (el.tagName === 'SELECT') {
        // A select whose first option is the "Choose..." placeholder reports that
        // placeholder's value, which core leaves empty. Anything else is a choice.
        return String(el.value ?? '').trim() !== '';
    }
    return String(el.value ?? '').trim() !== '';
};

/**
 * Every control in this form that must be answered before it may be submitted.
 *
 * Moodle marks a required field only by putting a red marker in the label
 * addon - there is no class on the wrapper and no `required` attribute on most
 * controls (see lib/form/templates/element-template.mustache). So three signals
 * are accepted, and a field matching any of them is in:
 *
 * - the HTML5 `required` attribute, where the element type sets it;
 * - `aria-required`, which the autocomplete and date selectors use;
 * - a `.fitem` wrapper containing the red required marker.
 *
 * `extra` covers controls the specification treats as mandatory but Moodle does
 * not mark, the Terms and Conditions checkbox being the reason this parameter
 * exists: it is an advcheckbox with no required rule, because it is validated
 * server-side in local_profilefields.
 *
 * @param {HTMLFormElement} form
 * @param {String[]} extra Additional CSS selectors, resolved within the form.
 * @return {HTMLElement[]}
 */
const requiredIn = (form, extra) => {
    const found = new Set();

    form.querySelectorAll('[required], [aria-required="true"]').forEach((el) => found.add(el));

    form.querySelectorAll('.fitem').forEach((item) => {
        if (!item.querySelector('.form-label-addon .text-danger')) {
            return;
        }
        item.querySelectorAll('input, select, textarea').forEach((el) => found.add(el));
    });

    extra.forEach((selector) => {
        try {
            form.querySelectorAll(selector).forEach((el) => found.add(el));
        } catch (e) {
            // A selector we cannot parse simply contributes nothing.
        }
    });

    return [...found].filter((el) => isLive(el));
};

/**
 * Wire one form: watch its fields, and mirror their state onto its buttons.
 *
 * @param {HTMLFormElement} form
 * @param {Object} config
 * @param {String[]} config.extraRequired Selectors for fields Moodle does not mark.
 * @param {String} config.hint Tooltip shown while the button is held down.
 * @return {void}
 */
const gate = (form, config) => {
    const submits = submitsOf(form);
    if (!submits.length) {
        return;
    }

    const refresh = () => {
        let complete;
        try {
            complete = requiredIn(form, config.extraRequired).every((el) => isAnswered(el, form));
        } catch (e) {
            // Rule 3: never leave a button we cannot reason about switched off.
            complete = true;
        }

        submits.forEach((button) => {
            button.disabled = !complete;
            button.classList.toggle('nit-gate-blocked', !complete);
            if (complete) {
                button.removeAttribute('title');
                button.removeAttribute('aria-describedby');
            } else if (config.hint) {
                button.setAttribute('title', config.hint);
            }
        });
    };

    // 'input' catches typing, 'change' catches selects, checkboxes, radios and
    // anything set programmatically that bothers to fire it. Both are delegated
    // from the form so fields added later - a repeated group, a shown conditional
    // - are covered without re-wiring.
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);

    // Autofill and password managers frequently populate fields without firing
    // either event. A short poll over the first few seconds costs nothing and
    // avoids a button that stays down over a form the browser has already filled.
    let ticks = 0;
    const poll = setInterval(() => {
        refresh();
        if (++ticks >= 10) {
            clearInterval(poll);
        }
    }, 400);

    // Releasing on submit matters: a disabled button is not sent with the form,
    // and Moodle reads the submit element's name on some screens.
    form.addEventListener('submit', () => {
        clearInterval(poll);
        submits.forEach((button) => {
            button.disabled = false;
        });
    });

    refresh();
};

/**
 * Gate every opted-in form on the page.
 *
 * A form opts in one of two ways. Our own plugin forms carry `data-nit-gate` in
 * their own markup. Core's forms cannot be marked from PHP - the sign-up and
 * login screens are core templates - so the caller names them in `config.forms`
 * and they are stamped here instead. Both routes converge on the same attribute,
 * so there is still exactly one definition of "gated".
 *
 * @param {Object} config
 * @param {String[]} [config.forms] Selectors for core forms to opt in.
 * @param {String[]} [config.extraRequired] Selectors for fields Moodle does not mark.
 * @param {String} [config.hint] Tooltip shown while a button is held down.
 * @return {void}
 */
export const init = (config = {}) => {
    const settings = {
        extraRequired: config.extraRequired || [],
        hint: config.hint || '',
    };

    (config.forms || []).forEach((selector) => {
        try {
            document.querySelectorAll(selector).forEach((form) => {
                if (form.tagName === 'FORM') {
                    form.setAttribute(OPT_IN, '');
                }
            });
        } catch (e) {
            // An unusable selector opts nothing in, which is the safe direction.
        }
    });

    document.querySelectorAll(`form[${OPT_IN}]`).forEach((form) => {
        if (form.classList.contains(DONE)) {
            return;
        }
        form.classList.add(DONE);
        gate(form, settings);
    });
};
