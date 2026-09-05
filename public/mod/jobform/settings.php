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
 * Admin settings for mod_jobform.
 *
 * One setting: whether the applicant gets an acknowledgement email.
 *
 * The reviewer notification next to it is a Moodle message provider
 * (`mod_jobform_submission`) and is therefore already switchable from the site's
 * notification screens. The applicant acknowledgement is not — it is sent with
 * email_to_user() because it is a plain transactional mail with no bell channel
 * and no per-user preference to honour — so it needs a switch of its own, and
 * this is it. Site administration › Plugins › Local plugins › Event
 * notifications reads and writes exactly this setting, so the two agree.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox(
        'mod_jobform/notifyapplicant',
        get_string('notifyapplicant', 'mod_jobform'),
        get_string('notifyapplicant_desc', 'mod_jobform'),
        1
    ));
}
