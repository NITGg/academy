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
