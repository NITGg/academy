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
 * Strings for local_nit_lessons.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Lessons';
$string['local_nit_lessons:request'] = 'Request lessons';
$string['local_nit_lessons:teach'] = 'Teach and run lessons';
$string['local_nit_lessons:managesettings'] = 'Manage NIT lesson settings';

// Settings page.
$string['settings'] = 'Academy Settings';
$string['deadlines'] = 'Lesson deadlines';
$string['financial'] = 'Financial settings';
$string['min_booking_minutes'] = 'Minimum booking lead time (minutes)';
$string['cancel_deadline_minutes'] = 'Student cancellation deadline (minutes before start)';
$string['update_deadline_minutes'] = 'Time-update deadline (minutes before start)';
$string['start_allowed_minutes'] = 'Lesson start allowed (minutes before start)';
$string['complete_allowed_minutes'] = 'Completion allowed after start (minutes)';
$string['absence_report_minutes'] = 'Absence reporting wait (minutes)';
$string['expiry_reminder_days'] = 'Package expiry reminder (days, 0 = off)';
$string['teacher_percent'] = 'Teacher earning percentage';
$string['platform_percent'] = 'Platform earning percentage';
$string['savesettings'] = 'Save settings';

// Errors.
$string['err_subjectrequired'] = 'A subject is required.';
$string['err_noterequired'] = 'A note is required.';
$string['err_selfbooking'] = 'You cannot book a lesson with yourself.';
$string['err_teachernotfound'] = 'Teacher not found.';
$string['err_noflex'] = 'You need an active package with available Flex.';
$string['err_forbidden'] = 'You are not allowed to perform this action.';
$string['err_badstate'] = 'The lesson is not in a state that allows this action.';
$string['err_badaction'] = 'Unknown action.';
$string['err_notime'] = 'A valid time is required.';
$string['err_minbooking'] = 'The lesson is too soon; respect the minimum booking lead time.';
$string['err_timeconflict'] = 'The teacher already has a lesson at that time.';
$string['err_tooearlytostart'] = 'It is too early to start this lesson.';
$string['err_completetooearly'] = 'The lesson has not run long enough to be completed yet.';
$string['err_absencetooearly'] = 'It is too early to report an absence.';
$string['err_updatedeadline'] = 'The time-update deadline has passed.';
$string['err_updatepending'] = 'There is already a pending time-update request.';
$string['err_noupdaterequest'] = 'There is no pending time-update request.';
$string['err_reasonrequired'] = 'A reason is required.';
$string['err_lessonnotfound'] = 'Lesson not found.';
$string['err_settingnegative'] = 'Values must be zero or greater.';
$string['err_percenttotal'] = 'The teacher and platform percentages must total 100.';
$string['err_postrequired'] = 'This action requires a POST request.';
$string['err_unknownfunction'] = 'Unknown API function.';

// Privacy.
$string['privacy:metadata:nit_lesson'] = 'Lessons between a student and a teacher.';
$string['privacy:metadata:nit_lesson:studentid'] = 'The student in the lesson.';
$string['privacy:metadata:nit_lesson:teacherid'] = 'The teacher in the lesson.';
$string['privacy:metadata:nit_lesson:note'] = 'The note the student attached to the request.';
$string['privacy:metadata:nit_lesson:timecreated'] = 'When the lesson was requested.';
$string['privacy:metadata:nit_lesson_proposal'] = 'Times proposed during lesson negotiation.';
$string['privacy:metadata:nit_lesson_proposal:proposedby'] = 'The user who proposed the time.';
$string['privacy:metadata:nit_lesson_proposal:proposed_time'] = 'The proposed time.';
$string['privacy:metadata:nit_lesson_proposal:timecreated'] = 'When the time was proposed.';
