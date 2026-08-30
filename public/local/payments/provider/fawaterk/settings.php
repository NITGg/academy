<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('paymentprovider_fawaterk', get_string('pluginname', 'paymentprovider_fawaterk'));

    // Where to point the Fawaterk dashboard webhook. Read-only reminder.
    $settings->add(new admin_setting_heading(
        'paymentprovider_fawaterk/webhookinfo',
        get_string('webhook_heading', 'paymentprovider_fawaterk'),
        get_string('webhook_heading_desc', 'paymentprovider_fawaterk',
            (new moodle_url('/local/payments/webhook_json.php'))->out(false))
    ));

    // Sandbox mode — switches the API host to staging.fawaterk.com.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_fawaterk/sandbox_mode',
        get_string('sandbox_mode', 'paymentprovider_fawaterk'),
        get_string('sandbox_mode_desc', 'paymentprovider_fawaterk'),
        1
    ));

    // Vendor key (API key) — Bearer token and webhook HMAC secret.
    $settings->add(new admin_setting_configpasswordunmask(
        'paymentprovider_fawaterk/vendor_key',
        get_string('vendor_key', 'paymentprovider_fawaterk'),
        get_string('vendor_key_desc', 'paymentprovider_fawaterk'),
        ''
    ));

    // Provider key — only needed for the JS iframe plugin, kept for completeness.
    $settings->add(new admin_setting_configpasswordunmask(
        'paymentprovider_fawaterk/provider_key',
        get_string('provider_key', 'paymentprovider_fawaterk'),
        get_string('provider_key_desc', 'paymentprovider_fawaterk'),
        ''
    ));

    // Live API base URL.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/base_url',
        get_string('base_url', 'paymentprovider_fawaterk'),
        get_string('base_url_desc', 'paymentprovider_fawaterk'),
        'https://app.fawaterk.com',
        PARAM_URL
    ));

    // Sandbox API base URL.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/sandbox_url',
        get_string('sandbox_url', 'paymentprovider_fawaterk'),
        get_string('sandbox_url_desc', 'paymentprovider_fawaterk'),
        'https://staging.fawaterk.com',
        PARAM_URL
    ));

    // Charge a method directly instead of showing Fawaterk's hosted picker.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_fawaterk/auto_select_method',
        get_string('auto_select_method', 'paymentprovider_fawaterk'),
        get_string('auto_select_method_desc', 'paymentprovider_fawaterk'),
        1
    ));

    // Which method wins when the account has several enabled.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/method_priority',
        get_string('method_priority', 'paymentprovider_fawaterk'),
        get_string('method_priority_desc', 'paymentprovider_fawaterk'),
        '2,4,3',
        PARAM_TEXT
    ));

    // How long an offline reference code (Fawry/Meeza) stays payable.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/reference_ttl_days',
        get_string('reference_ttl_days', 'paymentprovider_fawaterk'),
        get_string('reference_ttl_days_desc', 'paymentprovider_fawaterk'),
        '3',
        PARAM_INT
    ));

    // Fallback phone for buyers with no phone on their Moodle profile.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/default_phone',
        get_string('default_phone', 'paymentprovider_fawaterk'),
        get_string('default_phone_desc', 'paymentprovider_fawaterk'),
        '01000000000',
        PARAM_TEXT
    ));

    // Fallback address for buyers with no address on their Moodle profile.
    $settings->add(new admin_setting_configtext(
        'paymentprovider_fawaterk/default_address',
        get_string('default_address', 'paymentprovider_fawaterk'),
        get_string('default_address_desc', 'paymentprovider_fawaterk'),
        'N/A',
        PARAM_TEXT
    ));

    // Let Fawaterk email the invoice to the buyer.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_fawaterk/send_email',
        get_string('send_email', 'paymentprovider_fawaterk'),
        get_string('send_email_desc', 'paymentprovider_fawaterk'),
        0
    ));

    // Let Fawaterk SMS the invoice to the buyer.
    $settings->add(new admin_setting_configcheckbox(
        'paymentprovider_fawaterk/send_sms',
        get_string('send_sms', 'paymentprovider_fawaterk'),
        get_string('send_sms_desc', 'paymentprovider_fawaterk'),
        0
    ));
}
