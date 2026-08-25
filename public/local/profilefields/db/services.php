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
];
