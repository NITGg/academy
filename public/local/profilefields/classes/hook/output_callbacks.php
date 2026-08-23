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

namespace local_profilefields\hook;

use core\hook\output\before_standard_head_html_generation;
use local_profilefields\manager;

/**
 * Output hook callbacks - the profile edit half of the field layout.
 *
 * The sign-up form has `core_login_extend_signup_form()`; the profile edit form
 * has no equivalent, and `useredit_shared_definition()` cannot be reached from a
 * plugin at all. So the core fields switched off there are hidden with a
 * stylesheet instead of being removed from the form.
 *
 * That difference is worth knowing: a field hidden this way is still submitted
 * and still keeps whatever value the account already has. It is a way to keep an
 * unused box out of the user's way, not a way to stop the field existing - which
 * is exactly what the management page tells the admin.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output_callbacks {

    /**
     * Hide the core fields that are configured off for the profile edit form.
     *
     * @param before_standard_head_html_generation $hook the output hook
     * @return void
     */
    public static function hide_profile_fields(before_standard_head_html_generation $hook): void {
        global $PAGE, $CFG;

        if (!isset($PAGE) || during_initial_install() || !empty($CFG->upgraderunning)) {
            return;
        }

        // Both the self-service and the admin-side account editors are built from
        // useredit_shared_definition(), so both carry the same element ids.
        if (!in_array($PAGE->pagetype, ['user-edit', 'user-editadvanced'], true)) {
            return;
        }

        $selectors = manager::profile_hidden_selectors();
        if (empty($selectors)) {
            return;
        }

        $hook->add_html(\html_writer::tag(
            'style',
            implode(',', $selectors) . '{display:none !important;}',
            ['id' => 'local-profilefields-hidden', 'type' => 'text/css']
        ));
    }
}
