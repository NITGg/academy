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
    // The site footer, from the Footer tab of the Site pages manager. Pre-login
    // like the two above, because the footer is on the site's public pages and the
    // app wants it on its own. Read it once at start-up and cache it; it changes
    // only when an administrator edits that tab.
    'local_profilefields_get_footer' => [
        'classname'     => 'local_profilefields\external\get_footer',
        'methodname'    => 'execute',
        'description'   => 'The site footer as data: the contact rows, the link columns, the social links, the logo '
            . 'and the copyright line, already resolved to one language.',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => false,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_resend_confirmation' => [
        'classname'     => 'local_profilefields\external\resend_confirmation',
        'methodname'    => 'execute',
        'description'   => 'Send the confirmation link again, for the Resend button on the app\'s confirmation '
            . 'screen. Rate-limited per account, and deliberately unable to tell a caller whether an address is '
            . 'registered.',
        'type'          => 'write',
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
    // Call this straight after login. An account created outside the sign-up form
    // (a Google sign-in) was never asked for the phone, the country or the terms,
    // so the app has to finish the job before letting the user browse. Save the
    // answers with local_profilefields_update_profile - there is no second writer.
    'local_profilefields_get_completion_status' => [
        'classname'   => 'local_profilefields\external\get_completion_status',
        'methodname'  => 'execute',
        'description' => 'What the signed-in user still owes the sign-up flow: whether their registration is '
            . 'complete, and if not, the outstanding fields (in sign-up order) plus whether the site policies '
            . 'still need accepting.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],

    // The account screen - /local/profilefields/account.php and the two panes
    // that hang off it. This is a different screen from /user/edit.php, which the
    // three functions above describe: it shows only the core fields the
    // administrator has placed on the profile, never offers the e-mail address as
    // a box to type in, and adds a security pane and a delete pane that core has
    // no equivalent of. Saving still goes through
    // local_profilefields_update_profile; only the address and the deletion have
    // writers of their own, because both of them have to ask for the password
    // first.
    'local_profilefields_get_account_menu' => [
        'classname'   => 'local_profilefields\external\get_account_menu',
        'methodname'  => 'execute',
        'description' => 'The account screen\'s own navigation: the entries, in order, localised, and '
            . 'only the ones whose plugin is actually installed.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_get_account_profile' => [
        'classname'   => 'local_profilefields\external\get_account_profile',
        'methodname'  => 'execute',
        'description' => 'The account screen\'s profile pane as this site draws it: the fields in their '
            . 'sections, each with its label, value, display value, options and lock, plus the e-mail row '
            . 'and the profile-picture control.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_request_email_change' => [
        'classname'   => 'local_profilefields\external\request_email_change',
        'methodname'  => 'execute',
        'description' => 'Start an e-mail address change behind the account password. Nothing is applied '
            . 'until the confirmation link sent to the new address is opened.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_get_security' => [
        'classname'   => 'local_profilefields\external\get_security',
        'methodname'  => 'execute',
        'description' => 'The security pane: whether this account has a password here to change, when it '
            . 'last changed, what changing it costs, and the site\'s password policy.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_get_delete_account_info' => [
        'classname'   => 'local_profilefields\external\get_delete_account_info',
        'methodname'  => 'execute',
        'description' => 'Whether this account may delete itself, and the warning to show before it does.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_profilefields_delete_account' => [
        'classname'   => 'local_profilefields\external\delete_account',
        'methodname'  => 'execute',
        'description' => 'Delete the calling account, behind the same password and typed confirmation the '
            . 'web form asks for. Anonymises rather than hard-deletes, and destroys every token the '
            . 'account held - including the caller\'s.',
        'type'        => 'write',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
