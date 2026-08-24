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
 * English strings for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sign-up and profile fields';
$string['managefields'] = 'Sign-up and profile field layout';

// Tabs.
$string['tabregister'] = 'Register page';
$string['tablogin'] = 'Login page';
$string['tabprofile'] = 'Profile page';
$string['tabregister_intro'] = 'Choose which fields a new user fills in when creating an account, in what order, and what each one is called.';
$string['tablogin_intro'] = 'The login page only ever asks for an identifier and a password. These are the few things around it that can be turned on or off.';
$string['tabprofile_intro'] = 'Choose which profile fields a user is allowed to edit themselves. Whether a field appears, is required or must be unique is managed on the sign-up tab (for the register form) and on each field\'s own "Edit profile field" page.';

// Table columns.
$string['colfield'] = 'Field';
$string['colshow'] = 'Show';
$string['colrequired'] = 'Required';
$string['colunique'] = 'Unique';
$string['colcanedit'] = 'User can edit';
$string['colrename'] = 'Label';
$string['renamefield'] = 'Rename';
$string['renameoncore'] = 'Rename on the field page';
$string['fixedbycore'] = 'Fixed by Moodle - not configurable here.';

// Row badges.
$string['badgebuiltin'] = 'Built-in';
$string['badgecustom'] = 'Custom';
$string['badgespecial'] = 'Behaviour';

// Country from phone (task3).
$string['countryfromphone'] = 'Fill Country from the phone field';
$string['countryfromphone_desc'] = 'On the sign-up form, the Country box follows the country chosen in the phone field, so the user picks it once.';

// IP match (task4).
$string['ipmatchheading'] = 'Location check';
$string['ipmatchphone'] = 'Require the sign-up country to match the visitor\'s location';
$string['ipmatchphone_desc'] = 'When on, a new account is only created if the visitor\'s IP address resolves to the same country as the phone number they entered. Users on a VPN or roaming would be blocked, so use with care.';
$string['ipmatchonline'] = 'No setup needed: this uses a free online lookup to find the visitor\'s country. For faster, self-hosted lookups you can install a local GeoIP database in <a href="{$a}">Location &gt; IP address lookup</a>, and it will be used instead. If a lookup ever fails, the sign-up is allowed (never wrongly blocked).';
$string['ipmatchgeoip'] = 'A local GeoIP database is configured, so it is used for the lookup. If a lookup fails, the sign-up is allowed (never wrongly blocked).';
$string['ipmismatch'] = 'Your location does not match the country of the phone number you entered.';

// Username.
$string['usernameheading'] = 'Username';
$string['usernamefromemail'] = 'Build the username from the email address (hide the Username box)';
$string['usernamesource'] = 'Username taken from';
$string['usernamesourceemail'] = 'The whole email address';
$string['usernamesourcelocalpart'] = 'The part before the "@"';

// Terms & privacy.
$string['termsheading'] = 'Terms and privacy policy';
$string['termsnative'] = 'Consent to the terms and privacy policy is handled by Moodle\'s built-in Policies tool, which shows the documents to a new user before the account is created. This plugin does not change that behaviour.';
$string['termson'] = 'Policies are active (site policy handler is set to the Policies tool). New users must accept them during sign-up.';
$string['termsoff'] = 'The Policies tool is installed but not selected as the site policy handler, so new users are not yet asked to accept anything.';
$string['termsmanage'] = 'Manage policy documents';
$string['termssettings'] = 'Site policy settings';
$string['termsnotool'] = 'Moodle\'s Policies tool is not installed, so the checkbox will show plain wording with no document links.';
$string['consentenable'] = 'Show an agreement checkbox on the register form';
$string['consentenable_desc'] = 'When on, a required "I agree to the policies" checkbox is added to the sign-up form itself (with the documents linked from it), instead of Moodle\'s separate acceptance page. The documents are still written and versioned in Moodle\'s Policies tool.';
$string['consentlabel'] = 'I agree to the {$a}.';
$string['consentlabelplain'] = 'I agree to the terms of use and the privacy policy.';
$string['consentrequired'] = 'You must agree to the policies before you can create an account.';
$string['and'] = 'and';
$string['termsdocsfound'] = 'The checkbox will link to these policy documents:';
$string['termsdocsnone'] = 'No policy documents for guests are defined yet. Create them (below) and they will be linked from the checkbox automatically; until then the checkbox shows plain wording.';
$string['termsdoubleask'] = 'Moodle\'s Policies tool is also set to ask on a separate page, so users would be asked twice. While the inline checkbox is on, open <a href="{$a}">Users &gt; Privacy and policies &gt; Policy settings</a> and set the "Site policy handler" to "Default (based on the site policy setting)".';
$string['termspolicysettings'] = 'Policy settings';

// Login tab.
$string['loginselfregister'] = 'Allow new users to create their own account';
$string['loginselfregister_desc'] = 'Shows the "Create new account" button on the login page (email-based self registration).';
$string['loginguest'] = 'Show the "Log in as a guest" button';
$string['loginguest_desc'] = 'Lets visitors browse guest-accessible courses without an account.';
$string['loginremember'] = 'Remember the username';
$string['loginremember_desc'] = 'Ticks and pre-fills the username on the login page on the next visit.';

// Provisioning.
$string['provisionheading'] = 'Recommended fields';
$string['provisionintro'] = 'The academy\'s recommended set is not all present yet ({$a} missing). Create the missing fields in one step; existing fields are left untouched.';
$string['provisionbutton'] = 'Create the recommended fields';
$string['provisiondone'] = '{$a} field(s) created.';
$string['provisionallset'] = 'All recommended fields are present.';
$string['provisionnophone'] = 'The Phone field type plugin (profilefield_phone) is not installed, so the phone field cannot be created. Install it, then run this again.';
$string['academycategory'] = 'Additional details';

// Core headings.
$string['corefieldsheading'] = 'Built-in Moodle fields';
$string['customfieldsheading'] = 'Custom profile fields';
$string['optionalcorefields'] = 'Optional section (ID number, institution, department, phone, address)';

// Provisioned field names (Arabic side supplies the translation).
$string['fieldphone'] = 'Phone';
$string['fieldnationality'] = 'Nationality';
$string['fieldgender'] = 'Gender';
$string['fielddateofbirth'] = 'Date of birth';
$string['fieldjobtitle'] = 'Job title';
$string['fieldcompany'] = 'Company';
$string['fieldindustry'] = 'Industry';
$string['fieldeducation'] = 'Education';
$string['fieldnationalid'] = 'National ID';
$string['fieldpassport'] = 'Passport';

// Instructor fields.
$string['instructorcategory'] = 'Instructor Fields';
$string['instructorheading'] = 'Instructor fields';
$string['instructorintro'] = 'The instructor profile set is not all present yet ({$a->count} missing). Create the missing fields in "{$a->category}" in one step; existing fields are left untouched.';
$string['instructorbutton'] = 'Create the instructor fields';
$string['instructordone'] = '{$a} instructor field(s) created.';
$string['instructorallset'] = 'All instructor fields are present.';
$string['instructornofile'] = 'The File field type plugin (profilefield_file) is not installed, so "Cover image" and "Resume" will be created as URL text fields instead of real uploads. Install it first if you want people to upload the files themselves.';

// Reasons a requested field is never provisioned.
$string['skipcorename'] = 'Built into Moodle - first name and surname.';
$string['skipcoreemail'] = 'Built into Moodle - email address (already unique site-wide).';
$string['skipcorecountry'] = 'Built into Moodle - country.';
$string['skipcorepicture'] = 'Built into Moodle - the user picture.';
$string['skipexisting'] = 'Already exists as the custom field "{$a}".';

// Instructor field names (Arabic side supplies the translation).
$string['fieldcoverimage'] = 'Cover image';
$string['fieldbiography'] = 'Biography';
$string['fieldqualifications'] = 'Qualifications';
$string['fieldcertificates'] = 'Certificates';
$string['fieldexperience'] = 'Experience';
$string['fieldspecialization'] = 'Specialization';
$string['fieldlanguages'] = 'Languages';
$string['fieldlinkedin'] = 'LinkedIn';
$string['fieldwebsite'] = 'Website';
$string['fieldfacebook'] = 'Facebook';
$string['fieldinstagram'] = 'Instagram';
$string['fieldtwitter'] = 'Twitter';
$string['fieldyoutube'] = 'YouTube';
$string['fieldawards'] = 'Awards';
$string['fieldyearsofexperience'] = 'Years of experience';
$string['fieldresume'] = 'Resume';

$string['privacy:metadata'] ='The Sign-up and profile fields plugin only stores which fields a site shows. It stores no personal data.';
