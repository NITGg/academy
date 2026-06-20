<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academysessions', get_string('pluginname', 'local_academysessions'));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_api_key',
        'Bunny Stream API Key',
        'API key from bunny.net Account Settings',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_library_id',
        'Bunny Stream Library ID',
        'Video Library ID from Bunny Stream dashboard',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/bunny_cdn_hostname',
        'Bunny CDN Hostname',
        'CDN hostname for video playback (e.g. vz-abcdef-123.b-cdn.net)',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_academysessions/recording_expiry_days',
        'Recording Expiry (days)',
        'Number of days before recordings are automatically archived',
        '30',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
