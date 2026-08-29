<?php
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
 * Plugin callbacks for local_profilefields.
 *
 * `core_login_extend_signup_form()` collects `*_extend_signup_form()` out of every
 * plugin's lib.php, which is the one extension point core offers for the sign-up
 * form. It is called last in `login_signup_form::definition()`, so by the time we
 * are handed the form every core box and every custom profile field is in place.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply the configured field layout to the sign-up form.
 *
 * @param MoodleQuickForm $mform the sign-up form, mid-definition
 * @return void
 */
function local_profilefields_extend_signup_form($mform) {
    // Never touch the form while the site is mid-install or mid-upgrade: the
    // config table may not hold our settings yet.
    if (during_initial_install()) {
        return;
    }

    \local_profilefields\signup::apply($mform);
}

/**
 * Server-side validation for the sign-up form additions.
 *
 * Covers the inline policy-consent checkbox (an unticked advcheckbox submits 0,
 * which a client rule cannot catch), the registration IP deny list, and the
 * optional "IP country must match the phone country" rule.
 *
 * Both sign-up paths arrive here: the web form through
 * `login_signup_form::validation()`, and the app's web service through
 * `local_profilefields\signup_api::validate()`, which calls
 * `core_login_validate_extend_signup_form()` for exactly this reason. The third
 * registration path - `local/profilefields/complete.php`, where an account created
 * by Google answers the sign-up questions late - runs core's sign-up callbacks not
 * at all, so `complete_form::validation()` repeats these two checks by hand.
 *
 * @param array $data submitted sign-up values
 * @return array element name => error message
 */
function local_profilefields_validate_extend_signup_form($data) {
    if (during_initial_install()) {
        return [];
    }

    // A denied address is denied whatever else the form says, so this comes first
    // and short-circuits: there is no point telling someone which other boxes they
    // also got wrong on a form that will never be accepted from where they are.
    $blocked = \local_profilefields\signup::validate_ip_allowed();
    if ($blocked) {
        return $blocked;
    }

    $errors = [];

    if (\local_profilefields\manager::consent_enabled()
            && empty($data[\local_profilefields\signup::CONSENT])) {
        $errors[\local_profilefields\signup::CONSENT] = get_string('consentrequired', 'local_profilefields');
    }

    if (\local_profilefields\manager::ip_match_phone()) {
        $errors = array_merge($errors, \local_profilefields\signup::validate_ip_match($data));
    }

    return $errors;
}
