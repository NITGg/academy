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
 * Sign in with Apple (mobile) - admin settings.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_appleauth', get_string('pluginname', 'local_appleauth'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_appleauth/enabled',
        get_string('enabled', 'local_appleauth'),
        get_string('enabled_desc', 'local_appleauth'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_appleauth/clientids',
        get_string('clientids', 'local_appleauth'),
        get_string('clientids_desc', 'local_appleauth'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_appleauth/allowcreate',
        get_string('allowcreate', 'local_appleauth'),
        get_string('allowcreate_desc', 'local_appleauth'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_appleauth/newuserauth',
        get_string('newuserauth', 'local_appleauth'),
        get_string('newuserauth_desc', 'local_appleauth'),
        'oauth2',
        PARAM_ALPHANUMEXT
    ));

    // Which auth methods an existing account may be linked to via Apple sign-in.
    $settings->add(new admin_setting_configtext(
        'local_appleauth/allowedlinkauth',
        get_string('allowedlinkauth', 'local_appleauth'),
        get_string('allowedlinkauth_desc', 'local_appleauth'),
        'oauth2',
        PARAM_RAW
    ));

    // Per-IP request throttle (requests per minute; 0 disables).
    $settings->add(new admin_setting_configtext(
        'local_appleauth/ratelimit',
        get_string('ratelimit', 'local_appleauth'),
        get_string('ratelimit_desc', 'local_appleauth'),
        20,
        PARAM_INT
    ));
}
