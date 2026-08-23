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
 * English strings for local_nit_emails.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Purchase & registration emails';
$string['nit_emails:manage'] = 'Edit the purchase and registration email templates';
$string['privacy:metadata'] = 'The Purchase & registration emails plugin stores no personal data. It sends the site\'s existing user and purchase data to the recipient of each email.';

// Page.
$string['intro'] = 'These are the emails a student receives after buying a course, after subscribing to a plan, and after registering. Each one is written twice — once in English and once in Arabic — and the version sent is chosen from the recipient\'s own language (falling back to the site default language).';
$string['off'] = 'off';
$string['enabled'] = 'Send this email';
$string['enabled_help'] = 'Untick to stop this email being sent. The template is kept, so you can switch it back on later without rewriting it.';
$string['subject'] = 'Subject';
$string['body'] = 'Message';
$string['lang_en'] = 'English version';
$string['lang_ar'] = 'Arabic version';
$string['resetdefaults'] = 'Reset to the default wording';
$string['resetdone'] = 'This email has been reset to the default wording.';

// Events.
$string['event_course_purchase'] = 'Course purchased';
$string['event_course_purchase_desc'] = 'Sent once a course payment is confirmed and the student has been enrolled. It carries the course file summary: hours, instructor, audience, prerequisites, programme structure and the intended learning outcomes.';
$string['event_subscription_purchase'] = 'Subscription purchased';
$string['event_subscription_purchase_desc'] = 'Sent once a subscription plan becomes active. It carries the terms of the plan: what it covers, how long it runs, when it expires and what was paid.';
$string['event_registration'] = 'Registration completed';
$string['event_registration_desc'] = 'Sent once a new account is confirmed and its owner signs in for the first time. It confirms the account details and points to the first steps.';

// Preview and test.
$string['previewlang'] = 'Preview: {$a}';
$string['sendtestlang'] = 'Send test: {$a}';
$string['sendtest_desc'] = 'A test copy is filled with sample data and sent to your own address, {$a}.';
$string['test'] = 'TEST';
$string['testsent'] = 'A test copy has been sent to {$a}.';
$string['testfailed'] = 'The test email could not be sent to {$a}. Check the site\'s outgoing mail configuration.';

// Placeholder reference.
$string['placeholders'] = 'Placeholders you can use';
$string['placeholders_desc'] = 'Type a placeholder anywhere in the subject or the message and it is replaced with the real value when the email is sent. A placeholder with no value behind it — a course with no stated hours, for example — is replaced with a dash.';
$string['placeholder'] = 'Placeholder';

$string['ph_firstname'] = 'Recipient\'s first name.';
$string['ph_lastname'] = 'Recipient\'s last name.';
$string['ph_fullname'] = 'Recipient\'s full name.';
$string['ph_username'] = 'Recipient\'s username.';
$string['ph_email'] = 'Recipient\'s email address.';
$string['ph_sitename'] = 'Name of this site.';
$string['ph_siteurl'] = 'Address of this site.';
$string['ph_loginurl'] = 'Link to the sign-in page.';
$string['ph_dashboardurl'] = 'Link to the recipient\'s dashboard.';
$string['ph_date'] = 'Today\'s date, in the recipient\'s language.';
$string['ph_supportemail'] = 'The site support email address.';

$string['ph_coursename'] = 'Title of the purchased course.';
$string['ph_courseurl'] = 'Link that opens the course.';
$string['ph_coursesummary'] = 'The course summary text.';
$string['ph_coursestartdate'] = 'The course start date.';
$string['ph_totalhours'] = 'Total number of hours (course field "total_number_of_hours").';
$string['ph_instructors'] = 'Names of the teachers on the course.';
$string['ph_targetaudience'] = 'Target audience (course field "target_audience"), as a list.';
$string['ph_prerequisites'] = 'Prerequisites (course field "prerequisites"), as a list.';
$string['ph_coursecontent'] = 'Course content and programme structure: the sections, with how many activities each holds.';
$string['ph_ilos'] = 'Intended learning outcomes (course field "ilos" or "by_the_end_of_training"), as a list.';
$string['ph_amount'] = 'Amount paid.';
$string['ph_currency'] = 'Currency of the amount paid.';
$string['ph_orderid'] = 'Order number of the payment.';

$string['ph_subscriptionname'] = 'Name of the subscription plan.';
$string['ph_subscriptiondescription'] = 'Description of what the plan covers.';
$string['ph_durationdays'] = 'How long the plan runs.';
$string['ph_startdate'] = 'Date the subscription became active.';
$string['ph_expirydate'] = 'Date the subscription expires.';
$string['ph_seats'] = 'Number of seats (business plans only).';
$string['ph_subscriptiontype'] = 'Individual or business plan.';
$string['ph_coursesurl'] = 'Link to the course catalogue.';
$string['ph_mysubscriptionsurl'] = 'Link to the recipient\'s purchase history.';

$string['ph_profileurl'] = 'Link to the recipient\'s profile settings.';
$string['ph_browsecoursesurl'] = 'Link to the course catalogue.';

// Values used inside the rendered emails.
$string['subtype_normal'] = 'Individual';
$string['subtype_b2b'] = 'Business';
$string['nday'] = '1 day';
$string['ndays'] = '{$a} days';
$string['nhour'] = '1 hour';
$string['nhours'] = '{$a} hours';
$string['nactivity'] = '1 activity';
$string['nactivities'] = '{$a} activities';
$string['footer_automated'] = 'This message was sent automatically — there is no need to reply to it.';

// Sample data used by the preview and the test send.
$string['sample_coursename'] = 'Project Management Fundamentals';
$string['sample_coursesummary'] = 'A practical introduction to planning, running and closing a project.';
$string['sample_instructor'] = 'Dr Mona Adel';
$string['sample_audience1'] = 'Newly appointed team leaders';
$string['sample_audience2'] = 'Engineers moving into a coordination role';
$string['sample_prereq'] = 'A working knowledge of spreadsheets';
$string['sample_ilo1'] = 'Build a realistic project schedule and budget';
$string['sample_ilo2'] = 'Identify, rank and mitigate project risks';
$string['sample_module1'] = 'Module 1 — Initiating the project';
$string['sample_module2'] = 'Module 2 — Planning scope, time and cost';
$string['sample_planname'] = 'Annual full-access plan';
$string['sample_plandesc'] = 'Unlimited access to every course in the catalogue for a full year.';
