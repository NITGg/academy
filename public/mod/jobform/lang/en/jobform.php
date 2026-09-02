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
 * Strings for mod_jobform.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Job Form';
$string['modulename'] = 'Job Form';
$string['modulenameplural'] = 'Job Forms';
$string['modulename_help'] = 'The Job Form activity lets a student fill in and send a form (for example a job application) after finishing the course. A teacher or admin defines the fields, optionally gates the form behind a certificate, and reviews the submitted forms.';
$string['pluginadministration'] = 'Job Form administration';

// Capabilities.
$string['jobform:addinstance'] = 'Add a new Job Form activity';
$string['jobform:view'] = 'View a Job Form activity';
$string['jobform:submit'] = 'Fill in and send a Job Form';
$string['jobform:managefields'] = 'Manage a Job Form activity\'s fields';
$string['jobform:viewsubmissions'] = 'View submitted Job Forms';

// Settings form.
$string['jobformname'] = 'Activity name';
$string['availability'] = 'Availability';
$string['nocertificate'] = 'No certificate (always available)';
$string['linkedcertificate'] = 'Linked certificate';
$string['linkedcertificate_help'] = 'Pick a certificate activity from this course. The student can only fill in the form once they have been issued that certificate — i.e. after they finish the course. Choose "No certificate" to make the form always available.';
$string['allowresubmit'] = 'Allow the student to edit and resend';
$string['allowresubmit_help'] = 'If enabled, the student can change and resend their form after sending it. If disabled, the form is locked once sent.';

// View.
$string['generalsection'] = 'General';
$string['activityfieldsintro'] = 'These are the fields students will fill in for this activity. Editing them here only affects this activity.';
$string['confirmusedefaultfields'] = 'This will remove all of this activity\'s current fields and groups and replace them with the default template. Any answers already collected for the current fields will be deleted. Continue?';
$string['defaultfieldsapplied'] = 'The default fields have been applied to this activity.';
$string['certificaterequired'] = 'This form becomes available once you have earned the course certificate. Please finish the course first.';
$string['alreadysubmitted'] = 'You have already sent this form. Below is what you submitted.';
$string['noformfields'] = 'This form has no fields yet. Please check back later.';
$string['nothingtodisplay'] = 'There is nothing to display.';

// Student form buttons and messages.
$string['sendform'] = 'Send';
$string['savedraft'] = 'Save draft';
// AC-4.20.8 — the wording of the on-screen confirmation is contractual; keep it verbatim.
$string['formsent'] = 'Thank you. Your application has been received. Our team will contact you if your profile matches the role.';
$string['draftsaved'] = 'Your draft has been saved.';

// AC-4.20.8 — acknowledgement email to the applicant. The subject is contractual.
$string['ackemailsubject'] = 'We have received your application — EAAC';
$string['ackemailgreeting'] = 'Hi {$a},';
$string['ackemailbody'] = 'Thank you. Your application has been received. Our team will contact you if your profile matches the role.';
$string['ackemaildetails'] = 'Application: {$a->activity} ({$a->course})';
$string['ackemailfooter'] = 'This is an automatic message — please do not reply to it.';

// AC-4.20.8 — notification to the reviewer / administrator.
$string['messageprovider:submission'] = 'A new job application has been received';
$string['adminemailsubject'] = 'New job application: {$a->activity}';
$string['adminemailbody'] = '{$a->applicant} ({$a->email}) has sent an application for "{$a->activity}" in the course "{$a->course}".';
$string['adminemaillink'] = 'View the application';

// Validation.
$string['errornotnumber'] = 'Please enter a number.';
$string['errornotemail'] = 'Please enter a valid email address.';
$string['errornoturl'] = 'Please enter a valid link starting with http:// or https://.';
$string['errornotphone'] = 'Please enter a valid phone number (7 to 15 digits, an optional leading +).';
$string['errorinvalidcmid'] = 'No Job Form activity was found for course module id {$a}. Pass the Job Form activity\'s course-module id (cmid) — you can get the correct value from mod_jobform_get_jobforms_by_courses.';

// Date field help.
$string['dateoptional'] = 'Optional date';
$string['dateoptional_help'] = 'This date is optional. Tick the "Enable" checkbox to enter a date; leave it unticked to skip this field.';

// Privacy.
$string['privacy:metadata:jobform_submission'] = 'The forms a student submits for a Job Form activity.';
$string['privacy:metadata:jobform_submission:jobformid'] = 'The Job Form activity the submission belongs to.';
$string['privacy:metadata:jobform_submission:userid'] = 'The student who submitted the form.';
$string['privacy:metadata:jobform_submission:status'] = 'Whether the submission is a draft or has been sent.';
$string['privacy:metadata:jobform_submission:timemodified'] = 'When the submission was last changed.';
$string['privacy:metadata:jobform_submission_data'] = 'The answers within a submission.';
$string['privacy:metadata:jobform_submission_data:value'] = 'The value the student entered for a field.';
