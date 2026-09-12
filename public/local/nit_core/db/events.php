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
 * Event observers for local_nit_core.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Restore the language a logged-out visitor chose on the account screens: core's
    // set_login_session_preferences() unsets $SESSION->lang on every login, so without this
    // the site came back in the profile language (English, for the guest) the moment the
    // form was submitted. The choice is recorded by lang_callbacks::remember_chosen_language
    // (after_config, see db/hooks.php).
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_nit_core\hook\lang_callbacks::user_loggedin',
    ],
];
