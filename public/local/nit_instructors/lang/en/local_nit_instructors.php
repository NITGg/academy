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
 * English strings for local_nit_instructors.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Instructor background';

// The group itself (AC-4.5.9).
$string['background'] = 'Academic and Professional Background';
$string['editbackground'] = 'Edit my background';
$string['viewpublic'] = 'View my public profile';
$string['nobackground'] = 'This instructor has not added a background yet.';
$string['notaninstructor'] = 'This page is for instructors only.';
$string['nosuchinstructor'] = 'No such instructor.';

// Fields.
$string['specialty'] = 'Exact specialty / subspecialty';
$string['specialty_en'] = 'Exact specialty / subspecialty (English)';
$string['specialty_ar'] = 'Exact specialty / subspecialty (Arabic)';
$string['specialtytoolong'] = 'Please keep this to {$a} characters or fewer.';
$string['years'] = 'Years of teaching experience';
$string['years_help'] = 'Whole years, from 0 to 60. Leave it at 0 to say nothing about it - the field is optional, like everything else in this group.';
$string['yearsvalue'] = '{$a} years';
$string['yearsrange'] = 'Please enter a whole number of years between 0 and {$a}.';
$string['coursestaught'] = 'Courses taught';
$string['coursestaught_help'] = 'This list comes from the courses an administrator has assigned you to. It cannot be edited here, and adding a course by hand is not possible - the list is always what you actually teach.';
$string['nocoursestaught'] = 'No courses assigned yet.';

// Repeating entries (AC-4.5.12).
$string['type_qualification'] = 'Academic qualifications';
$string['type_position'] = 'Key positions held';
$string['type_certification'] = 'Professional certifications and awards';
$string['entry_title_en'] = 'Title (English)';
$string['entry_title_ar'] = 'Title (Arabic)';
$string['entry_org_en'] = 'Awarding body / organisation (English)';
$string['entry_org_ar'] = 'Awarding body / organisation (Arabic)';
$string['entry_period_en'] = 'Year or period (English)';
$string['entry_period_ar'] = 'Year or period (Arabic)';
$string['entrynote'] = 'To remove an entry, clear all of its boxes. Entries appear to learners in the order they appear here, so to reorder them, move the text.';
$string['addmore'] = 'Add more entries';
$string['bilingualnote'] = 'Every field here is optional, and every one can be written in both languages. Where you fill in only one, that one is shown to everybody rather than leaving a blank.';
$string['submitforreview'] = 'Send for review';

// Review workflow (AC-4.5.14, AC-4.5.15).
$string['pendingnotice'] = 'Your changes have been sent for review and will appear once approved.';
$string['rejectednotice'] = 'Your changes were not approved. Reason: {$a}';
$string['noreasongiven'] = 'no reason was given';
$string['reviewqueue'] = 'Instructor background review';
$string['queueintro'] = 'Each change is shown beside the version it would replace. Learners keep seeing the current version until you approve.';
$string['queueempty'] = 'Nothing is waiting for review.';
$string['currentversion'] = 'Currently published';
$string['proposedversion'] = 'Proposed change';
$string['approve'] = 'Approve and publish';
$string['reject'] = 'Reject';
$string['approved'] = 'The change has been published.';
$string['rejected'] = 'The change was rejected and the instructor has been told why.';
$string['decisionfailed'] = 'That change could not be acted on - it may have been withdrawn or already decided.';
$string['decisionnote'] = 'Reason';
$string['decisionnoteplaceholder'] = 'Required when rejecting; shown to the instructor.';
$string['reasonrequired'] = 'Please give a reason. The instructor is shown it, and a rejection without one tells them nothing.';

// Capability.
$string['nit_instructors:review'] = 'Review and publish instructor background changes';

// Privacy.
$string['privacy:metadata:local_nit_instructors_profile'] = 'The academic and professional background an instructor publishes about themselves.';
$string['privacy:metadata:local_nit_instructors_profile:userid'] = 'The instructor the background belongs to.';
$string['privacy:metadata:local_nit_instructors_profile:specialtyen'] = 'The instructor\'s exact specialty, in English.';
$string['privacy:metadata:local_nit_instructors_profile:specialtyar'] = 'The instructor\'s exact specialty, in Arabic.';
$string['privacy:metadata:local_nit_instructors_profile:years'] = 'How many years the instructor has been teaching.';
$string['privacy:metadata:local_nit_instructors_profile:status'] = 'Whether this version is published, waiting for review, or was rejected.';
$string['privacy:metadata:local_nit_instructors_profile:decisionnote'] = 'The reason an administrator gave for their decision.';
$string['privacy:metadata:local_nit_instructors_entry'] = 'The qualifications, positions and awards listed on an instructor background.';
$string['privacy:metadata:local_nit_instructors_entry:titleen'] = 'The qualification, position or award, in English.';
$string['privacy:metadata:local_nit_instructors_entry:titlear'] = 'The qualification, position or award, in Arabic.';
$string['privacy:metadata:local_nit_instructors_entry:orgen'] = 'The awarding body or organisation, in English.';
$string['privacy:metadata:local_nit_instructors_entry:orgar'] = 'The awarding body or organisation, in Arabic.';
$string['privacy:metadata:local_nit_instructors_entry:perioden'] = 'The year or period, in English.';
$string['privacy:metadata:local_nit_instructors_entry:periodar'] = 'The year or period, in Arabic.';
