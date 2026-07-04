<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_googleauth', get_string('pluginname', 'local_googleauth'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_googleauth/clientid',
        get_string('clientid', 'local_googleauth'),
        get_string('clientid_desc', 'local_googleauth'),
        '',
        PARAM_RAW_TRIMMED
    ));
}
