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
 * Password strength meter + reveal ("eye") toggle for the sign-up form.
 *
 * Purely an affordance: the real gate stays server-side in
 * check_password_policy(). The meter mirrors the site's configured policy
 * (Site administration > Security > Site security settings) so the bar can
 * never say "strong" for a password the server would reject.
 *
 * Authored as a native ES module - no jQuery. Built to amd/build.
 *
 * @module     theme_nit/passwordstrength
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Number} Number of segments in the bar, and the top score. */
const LEVELS = 4;

/** @type {String} Marker class so a double init() never wires the same field twice. */
const DONE = 'nit-pw-enhanced';

/**
 * Count how many characters of each class a password holds.
 *
 * Mirrors the classes check_password_policy() counts server-side, so the client
 * and the server agree on what a digit / letter / symbol is.
 *
 * @param {String} password
 * @return {Object} Keys: length, digits, lower, upper, nonalphanum.
 */
const tally = (password) => {
    const count = (re) => (password.match(re) || []).length;
    return {
        length: [...password].length,
        digits: count(/\p{Nd}/gu),
        lower: count(/\p{Ll}/gu),
        upper: count(/\p{Lu}/gu),
        nonalphanum: count(/[^\p{Lu}\p{Ll}\p{Nd}]/gu),
    };
};

/**
 * Does the password satisfy every configured policy minimum?
 *
 * @param {Object} counts Output of tally().
 * @param {Object} policy Site policy minimums.
 * @return {Boolean}
 */
const meetsPolicy = (counts, policy) => counts.length >= policy.minlength
    && counts.digits >= policy.digits
    && counts.lower >= policy.lower
    && counts.upper >= policy.upper
    && counts.nonalphanum >= policy.nonalphanum;

/**
 * Score a password from 0 (empty) to 4 (strong).
 *
 * Length carries the most weight - it is the only property that actually costs
 * an attacker time - with character variety as a secondary bonus, and a penalty
 * for the two patterns that make a long password worthless: one repeated
 * character, and a straight keyboard/alphabet run.
 *
 * The score is capped at 1 ("weak") until the site policy is satisfied, so the
 * bar never promises more than the server will accept.
 *
 * @param {String} password
 * @param {Object} policy Site policy minimums.
 * @return {Number} 0..4
 */
const score = (password, policy) => {
    if (!password.length) {
        return 0;
    }

    const counts = tally(password);
    let points = 0;

    // Length. These thresholds sit above the policy floor on purpose: clearing
    // the minimum is the price of entry, not a sign of strength.
    if (counts.length >= 8) {
        points += 1;
    }
    if (counts.length >= 12) {
        points += 1;
    }
    if (counts.length >= 16) {
        points += 1;
    }

    // Variety: one point per character class actually used.
    points += [counts.digits, counts.lower, counts.upper, counts.nonalphanum]
        .filter((n) => n > 0).length;

    // "aaaaaaaaaaaa" and "abcdefgh" / "123456" are long but trivially guessed.
    if (/^(.)\1+$/u.test(password)) {
        points = 1;
    } else if (/(?:abcdef|qwerty|012345|123456|password)/i.test(password)) {
        points -= 2;
    }

    // 0..7 raw points folded onto the 1..4 scale.
    const level = Math.min(LEVELS, Math.max(1, Math.ceil(points / 2)));

    return meetsPolicy(counts, policy) ? level : Math.min(level, 1);
};

/**
 * Build the strength meter element.
 *
 * @param {String} label Accessible name for the bar.
 * @return {Object} Keys: meter, track, text.
 */
const buildMeter = (label) => {
    const meter = document.createElement('div');
    meter.className = 'nit-pw-meter';
    meter.dataset.level = '0';
    meter.hidden = true;

    const track = document.createElement('div');
    track.className = 'nit-pw-meter-track';
    track.setAttribute('role', 'progressbar');
    track.setAttribute('aria-label', label);
    track.setAttribute('aria-valuemin', '0');
    track.setAttribute('aria-valuemax', String(LEVELS));
    track.setAttribute('aria-valuenow', '0');

    for (let i = 0; i < LEVELS; i++) {
        const segment = document.createElement('span');
        segment.className = 'nit-pw-meter-seg';
        track.appendChild(segment);
    }

    const text = document.createElement('p');
    text.className = 'nit-pw-meter-label';
    text.setAttribute('aria-live', 'polite');

    meter.appendChild(track);
    meter.appendChild(text);

    return {meter, track, text};
};

/**
 * Build the reveal ("eye") toggle for a password box.
 *
 * @param {HTMLInputElement} input The password box it controls.
 * @param {Object} strings Translated labels.
 * @return {HTMLButtonElement}
 */
const buildReveal = (input, strings) => {
    const button = document.createElement('button');
    // Explicitly not a submit - it lives inside the sign-up form.
    button.type = 'button';
    button.className = 'nit-pw-reveal';
    button.setAttribute('aria-pressed', 'false');
    button.setAttribute('aria-label', strings.showpassword);
    button.setAttribute('title', strings.showpassword);
    if (input.id) {
        button.setAttribute('aria-controls', input.id);
    }
    button.innerHTML = '<i class="fa-solid fa-eye" aria-hidden="true"></i>';

    button.addEventListener('click', () => {
        const shown = input.type === 'text';
        input.type = shown ? 'password' : 'text';
        button.setAttribute('aria-pressed', shown ? 'false' : 'true');
        const label = shown ? strings.showpassword : strings.hidepassword;
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.querySelector('i').className = shown ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
        // Put the caret back - not having to retype is the point of the eye.
        input.focus();
    });

    return button;
};

/**
 * Wire the meter and the reveal toggle onto the sign-up password box.
 *
 * @param {Object} config Keys: policy (site minimums), strings (translated labels).
 * @return {void}
 */
export const init = (config) => {
    const input = document.querySelector('#id_password, .signupform input[name="password"]');
    if (!input || input.classList.contains(DONE)) {
        return;
    }
    input.classList.add(DONE);

    const policy = config.policy;
    const strings = config.strings;

    // Wrap the box so the eye can sit inside its inline-end edge.
    const field = document.createElement('div');
    field.className = 'nit-pw-field';
    input.parentNode.insertBefore(field, input);
    field.appendChild(input);
    field.appendChild(buildReveal(input, strings));

    const {meter, track, text} = buildMeter(strings.strength);
    field.parentNode.insertBefore(meter, field.nextSibling);

    const render = () => {
        const level = score(input.value, policy);
        meter.dataset.level = String(level);
        meter.hidden = level === 0;
        track.setAttribute('aria-valuenow', String(level));
        text.textContent = level === 0 ? '' : strings.levels[level];
    };

    // Tell the stylesheet the meter is live: the password-policy blurb is a form
    // row of its own (so out of reach of a sibling selector from in here) and
    // _login.scss pulls it up tight under the box - that gap is now the meter's.
    document.body.classList.add('nit-pw-metered');

    input.addEventListener('input', render);
    // Browsers restore values on back/forward and password managers fill
    // without firing 'input', so paint once on the way in too.
    render();
};
