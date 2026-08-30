<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Administrator settings for local_academy.
 *
 * The password-reset limits below used to be constants in
 * \local_academy\password_reset_manager, which made AC-4.4.4 and AC-4.4.5
 * ("a maximum of five reset requests", "after five incorrect code entries the
 * attempt is invalidated") untestable by an administrator: the numbers were
 * right but nobody outside the code could see or change them. They are settings
 * now. The constants remain as the shipped defaults, so a site that never opens
 * this page behaves exactly as before.
 *
 * The account-lockout limits AC-4.3.2 asks about are core's, and already
 * administrator-controlled under Site administration > Security > Site security
 * settings (Account lockout threshold / window / duration). They are deliberately
 * not duplicated here - two places to set one number is how they drift apart.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_academy', get_string('pluginname', 'local_academy'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_academy/passwordresetheading',
        get_string('settings_passwordreset', 'local_academy'),
        get_string('settings_passwordreset_desc', 'local_academy')
    ));

    // AC-4.4.4. Counted per email address, not per account, so that requesting
    // codes for an address that has no account is throttled the same way -
    // otherwise the endpoint answers "does this address exist?" by how fast it
    // gives up.
    $settings->add(new admin_setting_configtext(
        'local_academy/otprequestmax',
        get_string('settings_otprequestmax', 'local_academy'),
        get_string('settings_otprequestmax_desc', 'local_academy'),
        \local_academy\password_reset_manager::REQUEST_MAX,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configduration(
        'local_academy/otprequestwindow',
        get_string('settings_otprequestwindow', 'local_academy'),
        get_string('settings_otprequestwindow_desc', 'local_academy'),
        \local_academy\password_reset_manager::REQUEST_WINDOW
    ));

    // AC-4.4.5. Counted against the code itself, so burning one code does not
    // lock the account out of requesting another.
    $settings->add(new admin_setting_configtext(
        'local_academy/otpmaxattempts',
        get_string('settings_otpmaxattempts', 'local_academy'),
        get_string('settings_otpmaxattempts_desc', 'local_academy'),
        \local_academy\password_reset_manager::OTP_MAX_ATTEMPTS,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configduration(
        'local_academy/otpttl',
        get_string('settings_otpttl', 'local_academy'),
        get_string('settings_otpttl_desc', 'local_academy'),
        \local_academy\password_reset_manager::OTP_TTL
    ));
}
