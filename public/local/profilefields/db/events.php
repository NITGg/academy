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
 * Event observers for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Start the 24-hour clock on the confirmation link auth_email is about to
        // send (AC-4.2.10). There is no event for the send itself.
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_profilefields\observer::user_created',
    ],
    [
        // Confirmation logs the account in, and Moodle raises no event for the
        // confirmation itself, so this is where the verification counters retire.
        // Also where a ticked "Remember me" earns its token (AC-4.3.5).
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_profilefields\observer::user_loggedin',
    ],
    [
        // Signing out of a device withdraws that device's trust.
        'eventname' => '\core\event\user_loggedout',
        'callback'  => '\local_profilefields\observer::user_loggedout',
    ],
    [
        // A changed password must invalidate the credentials that could rebuild a
        // session without it (AC-4.3.11, AC-4.4.7).
        'eventname' => '\core\event\user_password_updated',
        'callback'  => '\local_profilefields\observer::user_password_updated',
    ],
];
