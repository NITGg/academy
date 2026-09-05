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
 * Event observers for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // The rules are enforced as rows in the recipient's own blocked-users list, which core
        // shows them and lets them delete. Without this observer a restricted student could
        // lift the policy on themselves from their own message preferences, so an unblock of a
        // pair a course still denies is put straight back.
        'eventname' => '\core\event\message_user_unblocked',
        'callback'  => '\local_msgrules\observer::message_user_unblocked',
    ],
    [
        // Enrolment is the input to every rule: joining a course is what decides who a student
        // may write to, and leaving it is what takes the permission away.
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\local_msgrules\observer::enrolment_changed',
    ],
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback'  => '\local_msgrules\observer::enrolment_changed',
    ],
    [
        // A suspended enrolment does not count, so a status change matters as much as adding
        // or removing one.
        'eventname' => '\core\event\user_enrolment_updated',
        'callback'  => '\local_msgrules\observer::enrolment_changed',
    ],
    [
        // Whether somebody counts as a teacher decides both whether they are restricted and
        // whether students may write to them, so a role change re-derives their pairs.
        'eventname' => '\core\event\role_assigned',
        'callback'  => '\local_msgrules\observer::role_changed',
    ],
    [
        'eventname' => '\core\event\role_unassigned',
        'callback'  => '\local_msgrules\observer::role_changed',
    ],
    [
        // A deleted course takes its override with it, otherwise the row lingers and a course
        // created later could inherit a setting nobody chose for it.
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\local_msgrules\observer::course_deleted',
    ],
    [
        // A brand-new account is on no course, so no restricted student may write to it - but
        // only once the rows saying so exist. Without this, every restricted student could
        // message each new sign-up until the nightly rebuild caught up.
        'eventname' => '\core\event\user_created',
        'callback'  => '\local_msgrules\observer::user_created',
    ],
    [
        // Core clears the account's own message_users_blocked rows; this drops our record of
        // the ones we owned so the managed table does not collect orphans.
        'eventname' => '\core\event\user_deleted',
        'callback'  => '\local_msgrules\observer::user_deleted',
    ],
];
