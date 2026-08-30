<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 */
$observers = [
    // There is no core "user confirmed" event (auth_email just sets confirmed=1),
    // so we use the user's first successful login as the "just confirmed" signal
    // and send the welcome message once.
    [
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_academy\observer::user_loggedin',
    ],

    // AC-4.3.4: core applies the lockout on the attempt that trips it but only
    // *reports* it on the attempt after, so the login page needs to be told what
    // the failed attempt did. Core triggers this event after login_attempt_failed()
    // has already written the lock, so the account state is settled by now.
    [
        'eventname' => '\core\event\user_login_failed',
        'callback'  => '\local_academy\observer::user_login_failed',
    ],

    // AC-4.5.1: a certificate must keep the name it was earned under, so the
    // holder's name is copied the moment the certificate is issued. Rendering
    // reads the copy; renaming the profile afterwards cannot reach back into it.
    [
        'eventname' => '\mod_customcert\event\issue_created',
        'callback'  => '\local_academy\observer::certificate_issued',
    ],
];
