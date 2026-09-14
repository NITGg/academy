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
 * Mobile app launch settings — the values served by getsettings.php.
 *
 * Every value here is published, unauthenticated, to anyone who requests
 * /local/multitopics/getsettings.php. Nothing secret belongs on this page;
 * the token in particular must be the read-only guest-browsing token created
 * by cli/app_guest_token.php, never an administrator's.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_multitopics', get_string('appsettings', 'local_multitopics'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading('local_multitopics/heading_access',
        get_string('heading_access', 'local_multitopics'),
        get_string('heading_access_desc', 'local_multitopics')));

    // Published as-is. PARAM_ALPHANUM because a Moodle token is a 32-char hex string.
    $settings->add(new admin_setting_configtext('local_multitopics/admin_token',
        get_string('admin_token', 'local_multitopics'),
        get_string('admin_token_desc', 'local_multitopics'), '', PARAM_ALPHANUM, 60));

    $settings->add(new admin_setting_configtext('local_multitopics/google_client_id',
        get_string('google_client_id', 'local_multitopics'),
        get_string('google_client_id_desc', 'local_multitopics'), '', PARAM_RAW_TRIMMED, 80));

    $settings->add(new admin_setting_configtext('local_multitopics/server_timeout_duration',
        get_string('server_timeout_duration', 'local_multitopics'),
        get_string('server_timeout_duration_desc', 'local_multitopics'), 10, PARAM_INT, 5));

    $settings->add(new admin_setting_heading('local_multitopics/heading_protection',
        get_string('heading_protection', 'local_multitopics'), ''));

    // Fail-secure on the app side: anything but a literal "0" keeps protection on.
    $settings->add(new admin_setting_configcheckbox('local_multitopics/prevent_screen_recording',
        get_string('prevent_screen_recording', 'local_multitopics'),
        get_string('prevent_screen_recording_desc', 'local_multitopics'), 1));

    $settings->add(new admin_setting_configcheckbox('local_multitopics/watermark',
        get_string('watermark', 'local_multitopics'),
        get_string('watermark_desc', 'local_multitopics'), 0));

    $settings->add(new admin_setting_configtext('local_multitopics/watermark_speed',
        get_string('watermark_speed', 'local_multitopics'),
        get_string('watermark_speed_desc', 'local_multitopics'), '0.002', PARAM_FLOAT, 8));

    $settings->add(new admin_setting_configtext('local_multitopics/watermark_fontsize',
        get_string('watermark_fontsize', 'local_multitopics'),
        get_string('watermark_fontsize_desc', 'local_multitopics'), '16', PARAM_FLOAT, 8));

    // Stored as "#rrggbb"; getsettings.php strips the "#" because the app parses the
    // bare six digits. Empty = key omitted = the app's animated colour cycle.
    $settings->add(new admin_setting_configcolourpicker('local_multitopics/watermark_color',
        get_string('watermark_color', 'local_multitopics'),
        get_string('watermark_color_desc', 'local_multitopics'), ''));

    $settings->add(new admin_setting_heading('local_multitopics/heading_store',
        get_string('heading_store', 'local_multitopics'),
        get_string('heading_store_desc', 'local_multitopics')));

    $settings->add(new admin_setting_configtext('local_multitopics/android_version',
        get_string('android_version', 'local_multitopics'),
        get_string('version_desc', 'local_multitopics'), '', PARAM_RAW_TRIMMED, 20));

    $settings->add(new admin_setting_configtext('local_multitopics/android_url',
        get_string('android_url', 'local_multitopics'), '', '', PARAM_URL, 80));

    $settings->add(new admin_setting_configtext('local_multitopics/ios_version',
        get_string('ios_version', 'local_multitopics'),
        get_string('version_desc', 'local_multitopics'), '', PARAM_RAW_TRIMMED, 20));

    $settings->add(new admin_setting_configtext('local_multitopics/ios_url',
        get_string('ios_url', 'local_multitopics'), '', '', PARAM_URL, 80));

    $settings->add(new admin_setting_heading('local_multitopics/heading_support',
        get_string('heading_support', 'local_multitopics'), ''));

    $settings->add(new admin_setting_configtext('local_multitopics/whatsapp_phone',
        get_string('whatsapp_phone', 'local_multitopics'),
        get_string('whatsapp_phone_desc', 'local_multitopics'), '', PARAM_RAW_TRIMMED, 20));

    // Plain text here; getsettings.php percent-encodes it for the wa.me URL.
    $settings->add(new admin_setting_configtextarea('local_multitopics/whatsapp_message',
        get_string('whatsapp_message', 'local_multitopics'),
        get_string('whatsapp_message_desc', 'local_multitopics'), '', PARAM_TEXT));
}
