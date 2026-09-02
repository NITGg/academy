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
 * Event observers for local_payments.
 *
 * @package    local_payments
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Forget the free-preview flag of a deleted activity. Course module ids are reused, and
    // a new activity inheriting a stale row would silently be free to the whole internet.
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => '\local_payments\event\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback'  => '\local_payments\event\observer::course_deleted',
    ],
];
