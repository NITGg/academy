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
 * Sign in with Apple (mobile) - language strings.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sign in with Apple (mobile)';
$string['enabled'] = 'Enable Apple mobile token endpoint';
$string['enabled_desc'] = 'Allow the mobile app to exchange a verified Sign in with Apple identity token for a Moodle web service token via /local/appleauth/token.php.';
$string['clientids'] = 'Allowed Apple client IDs';
$string['clientids_desc'] = 'Accepted "aud" values of the Apple identity token, one per line or comma-separated: the iOS app\'s <strong>bundle ID</strong> (native sign-in) and, if used, the <strong>Services ID</strong> (Android / web sign-in).';
$string['allowcreate'] = 'Auto-create users';
$string['allowcreate_desc'] = 'Create a new Moodle account when no account is linked to the Apple ID or matches its verified e-mail. If disabled, unknown Apple IDs are rejected.';
$string['newuserauth'] = 'Auth method for new users';
$string['newuserauth_desc'] = 'Authentication plugin assigned to auto-created accounts (e.g. oauth2 or manual). Must be an enabled auth method.';
$string['allowedlinkauth'] = 'Linkable auth methods';
$string['allowedlinkauth_desc'] = 'Comma-separated list of authentication methods an account may use to be signed into via Apple (e.g. oauth2). This prevents taking over a local password account that shares the same e-mail. Defaults to oauth2.';
$string['ratelimit'] = 'Rate limit (requests/minute per IP)';
$string['ratelimit_desc'] = 'Maximum token requests accepted from a single IP address per minute. Excess requests get HTTP 429. Set to 0 to disable throttling. Default 20.';
$string['cachedef_ratelimit'] = 'Apple sign-in request counters';
$string['cachedef_jwks'] = 'Apple public signing keys';
$string['privacy:metadata:local_appleauth_links'] = 'The Apple ID each account signs in with.';
$string['privacy:metadata:local_appleauth_links:userid'] = 'The Moodle account.';
$string['privacy:metadata:local_appleauth_links:sub'] = 'Apple\'s identifier for the user.';
$string['privacy:metadata:local_appleauth_links:email'] = 'The last e-mail Apple shared (may be a private-relay address).';
$string['privacy:metadata:local_appleauth_links:timecreated'] = 'When the Apple ID was first used.';
$string['privacy:metadata:local_appleauth_links:timemodified'] = 'When the Apple ID was last used.';
