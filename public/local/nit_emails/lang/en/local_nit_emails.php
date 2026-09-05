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

// AC-4.5.5 - the learner's email preferences.
$string['prefstitle'] = 'Email preferences';
$string['prefsintro'] = 'Choose which emails you would like to receive from us. Messages about your account and its security are always sent.';
$string['prefssaved'] = 'Your email preferences have been saved.';

$string['group_marketing'] = 'Offers and news';
$string['group_transactional'] = 'Your account and purchases';
$string['group_security'] = 'Security';

$string['kind_offers'] = 'Discounts and offers';
$string['kind_offers_desc'] = 'Coupon codes and limited-time offers on courses and packages.';
$string['kind_newcourses'] = 'New courses';
$string['kind_newcourses_desc'] = 'A note when we publish a course in a subject you have studied.';
$string['kind_newsletter'] = 'Newsletter';
$string['kind_newsletter_desc'] = 'Occasional news from the academy.';

$string['kind_registration'] = 'Welcome message';
$string['kind_registration_desc'] = 'Sent once, when your email address is confirmed.';
$string['kind_course_purchase'] = 'Course purchase confirmation';
$string['kind_course_purchase_desc'] = 'Confirms a course you have bought and how to start it.';
$string['kind_subscription_purchase'] = 'Package purchase confirmation';
$string['kind_subscription_purchase_desc'] = 'Confirms a package you have bought, what it unlocks and for how long.';
$string['kind_invoice'] = 'Invoices and receipts';
$string['kind_invoice_desc'] = 'The record of what you paid, which you may need for your own accounts.';
$string['kind_expiry'] = 'Access expiry reminders';
$string['kind_expiry_desc'] = 'A warning before access to a course or package runs out.';
$string['kind_accountsecurity'] = 'Account security alerts';
$string['kind_accountsecurity_desc'] = 'Password changes, sign-in attempts we refused, and account lock-outs. These are often the only sign that somebody else is using your account.';

// Event notifications page.
$string['events'] = 'Event notifications';
$string['events_intro'] = 'Every event this site can tell a learner about, and how. Tick <strong>Email</strong> to send an email when the event happens; tick <strong>Notification</strong> to show it on the bell in the header (which is also what reaches the mobile app). Unticking a channel stops it for everybody — recipients cannot switch it back on for themselves. Ticking one puts it back on by default and lets each person opt out again in their own notification preferences.';
$string['events_event'] = 'Event';
$string['events_filter'] = 'Find an event';
$string['events_filter_placeholder'] = 'Type part of an event or plugin name…';
$string['events_sendvia'] = '{$a->event}: send by {$a->channel}';
$string['events_forced'] = 'Always sent';
$string['events_providerdisabled'] = 'Switched off';
$string['eventssaved'] = 'Saved. {$a} event(s) changed.';
$string['eventsnochange'] = 'Nothing was changed.';
$string['events_channeloff'] = 'The {$a->channel} channel is switched off for the whole site, so nothing is delivered that way whatever is ticked below. Turn it back on under <a href="{$a->url}">Notification settings</a>.';
$string['events_seealso'] = 'These are the same settings as the site\'s <a href="{$a}">Notification settings</a> page, shown one event per row. Use that page for the finer states — allowing a channel without switching it on by default, or forcing one so nobody can opt out.';
$string['channel_email'] = 'Email';
$string['channel_popup'] = 'Notification';
