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
$string['tabprofile_intro'] = 'Choose which fields a user sees when editing their profile, and whether each one is required, unique, and editable by the user.';

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

// Username.
$string['usernameheading'] = 'Username';
$string['usernamefromemail'] = 'Build the username from the email address (hide the Username box)';
$string['usernamesource'] = 'Username taken from';
$string['usernamesourceemail'] = 'The whole email address';
$string['usernamesourcelocalpart'] = 'The part before the "@"';

// Terms & privacy.
$string['termsheading'] = 'Terms and privacy policy';
$string['termsmanage'] = 'Manage policy documents';
$string['consentenable'] = 'Show an agreement checkbox on the register form';
$string['consentenable_desc'] = 'When on, a required "I agree to the policies" checkbox is added to the sign-up form itself, instead of the separate acceptance page. The policy documents are still written and versioned in Moodle\'s Policies tool; only the tick moves onto the form.';
$string['consentlabel'] = 'I agree to the {$a}.';
$string['consentlabelplain'] = 'I agree to the terms of use and the privacy policy.';
$string['consentrequired'] = 'You must agree to the policies before you can create an account.';
$string['and'] = 'and';
$string['termsdocsfound'] = 'The checkbox will link to these policy documents:';
$string['termsdocsnone'] = 'No policy documents for guests are defined yet. Create them below and they will be linked from the checkbox automatically; until then the checkbox shows plain wording.';
$string['termsdoubleask'] = 'Moodle\'s Policies tool is also set to ask on a separate page, so users would be asked twice. While the inline checkbox is on, open <a href="{$a}">Privacy settings</a> and set the site policy handler to "Not set".';

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

$string['privacy:metadata'] = 'The Sign-up and profile fields plugin only stores which fields a site shows. It stores no personal data.';
