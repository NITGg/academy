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
 * English strings for profilefield_phone.
 *
 * @package    profilefield_phone
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Phone';
$string['defaultcountry'] = 'Default country';
$string['defaultcountry_help'] = 'The country dialling code shown first when the field is empty. If the site has IP address lookup configured (Site administration > Location), the visitor\'s own country is detected and used instead; this setting is the fallback.';
$string['invalidphone'] = 'Please enter a valid phone number.';

$string['privacy:metadata:profilefield_phone:userid'] = 'The ID of the user whose phone number this is.';
$string['privacy:metadata:profilefield_phone:fieldid'] = 'The ID of the phone profile field.';
$string['privacy:metadata:profilefield_phone:data'] = 'The phone number (country and number).';
$string['privacy:metadata:profilefield_phone:dataformat'] = 'The format of the phone number.';
$string['privacy:metadata:profilefield_phone:tableexplanation'] = 'Phone profile field data.';
