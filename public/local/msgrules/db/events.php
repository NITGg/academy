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
        // shows them and lets them delete. Without this observer a restricted user could lift
        // the site policy on themselves from their own message preferences, so an unblock of a
        // pair the matrix still denies is put straight back.
        'eventname' => '\core\event\message_user_unblocked',
        'callback'  => '\local_msgrules\observer::message_user_unblocked',
    ],
    [
        // Cohort membership is the input to every rule, so a change to it re-derives every
        // pair involving that one user - cheap, since it is one user against the roster.
        'eventname' => '\core\event\cohort_member_added',
        'callback'  => '\local_msgrules\observer::cohort_membership_changed',
    ],
    [
        'eventname' => '\core\event\cohort_member_removed',
        'callback'  => '\local_msgrules\observer::cohort_membership_changed',
    ],
    [
        // A new account starts in no cohort, so it needs its pairs derived immediately -
        // otherwise it could message everyone until the nightly rebuild caught up.
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
