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

// Management page.
$string['managefields'] = 'Sign-up and profile field layout';
$string['manageintro'] = 'Decide which fields a new account fills in on the <a href="{$a}">sign-up form</a>, and which ones an existing account sees when editing its profile. Use core\'s <em>User profile fields</em> page to create, edit and reorder custom fields; use this page to place them.';

// Username.
$string['usernameheading'] = 'Username';
$string['usernameintro'] = 'Moodle always needs a username. It does not always need to ask the person for one.';
$string['usernamefromemail'] = 'Build the username from the email address';
$string['usernamefromemail_help'] = 'When set to Yes, the Username box is removed from the sign-up form and the username is generated from the email address the person types. They then sign in with their email address.

Existing accounts are untouched, and administrators can still set a username by hand when creating an account.';
$string['usernamesource'] = 'Username taken from';
$string['usernamesource_help'] = 'Either the whole email address (ali@example.com becomes the username ali@example.com) or just the part before the "@" (which becomes ali). Either way a number is added if the name is already taken.';
$string['usernamesourceemail'] = 'The whole email address';
$string['usernamesourcelocalpart'] = 'The part before the "@"';

// Core fields.
$string['corefieldsheading'] = 'Built-in Moodle fields';
$string['corefieldsintro'] = 'The fields Moodle ships with. Leave the label empty to keep Moodle\'s own wording; the position number only affects the sign-up form, where fields are shown from the lowest number to the highest. Fields an account cannot work without - password, email and name - cannot be switched off.';
$string['optionalcorefields'] = 'Optional section (ID number, institution, department, phone, address)';
$string['labeloverrideplaceholder'] = 'Label';
$string['orderplaceholder'] = 'Pos.';

// Custom fields.
$string['customfieldsheading'] = 'Custom profile fields';
$string['customfieldsintro'] = 'Fields defined for this site.';
$string['customfieldsnone'] = 'No custom profile fields have been created yet.';
$string['createnewfield'] = 'Create a new field:';

// Placement.
$string['modeboth'] = 'Sign-up and profile';
$string['modesignup'] = 'Sign-up only';
$string['modeprofile'] = 'Profile only';
$string['modehidden'] = 'Hidden';
$string['modehiddencustom'] = 'Hidden (administrators only)';

$string['privacy:metadata'] = 'The Sign-up and profile fields plugin only stores which fields a site shows. It stores no personal data.';
