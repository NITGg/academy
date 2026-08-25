<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_profilefields_get_profile_fields' => [
        'classname'   => 'local_profilefields\external\get_profile_fields',
        'methodname'  => 'execute',
        'description' => 'Get all custom user profile fields with their option values (for menu fields).',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Sign-up. These two replace auth_email_get_signup_settings and
    // auth_email_signup_user, which describe and submit stock Moodle's sign-up form
    // rather than the one this site actually shows. Both are callable before login
    // (a visitor creating an account has no token yet), either with the shared
    // registration token through /webservice/rest/server.php or with no token at all
    // through /lib/ajax/service-nologin.php.
    'local_profilefields_get_signup_form' => [
        'classname'     => 'local_profilefields\external\get_signup_form',
        'methodname'    => 'execute',
        'description'   => 'Describe the sign-up form as this site renders it: the fields to show, in order, with their '
            . 'labels, requiredness and options, plus the flow settings (username from email, consent, country from phone).',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_get_policy_documents' => [
        'classname'     => 'local_profilefields\external\get_policy_documents',
        'methodname'    => 'execute',
        'description'   => 'Get the text of the policy documents shown on sign-up, so a client can render them itself '
            . 'instead of opening the tool_policy page.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_signup_user' => [
        'classname'     => 'local_profilefields\external\signup_user',
        'methodname'    => 'execute',
        'description'   => 'Create an account through the site\'s own sign-up flow: username derived from the email, '
            . 'consent enforced, city/country filled in, and every sign-up validation the web form runs.',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => false,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // Profile. These three are the profile-side twins of the sign-up pair above:
    // they describe and submit /user/profile.php and /user/edit.php as this site
    // renders them, so an app no longer has to open those pages in a WebView.
    // Core offers no alternative for the edit half - core_user_update_users needs
    // moodle/user:update, which an ordinary user editing their own profile does
    // not have.
    'local_profilefields_get_profile' => [
        'classname'   => 'local_profilefields\external\get_profile',
        'methodname'  => 'execute',
        'description' => 'Read a profile the way /user/profile.php shows it: the user\'s details, the custom '
            . 'profile fields this viewer may see, and the page\'s own section/row tree.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_get_profile_form' => [
        'classname'   => 'local_profilefields\external\get_profile_form',
        'methodname'  => 'execute',
        'description' => 'Describe the profile edit form as this site renders it: the fields to show, in order, '
            . 'with their labels, current values, requiredness, options and which of them the auth plugin has locked.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_update_profile' => [
        'classname'   => 'local_profilefields\external\update_profile',
        'methodname'  => 'execute',
        'description' => 'Save the profile through the site\'s own edit flow: the same capability checks, the same '
            . 'validation and the same email-change confirmation /user/edit.php applies.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
