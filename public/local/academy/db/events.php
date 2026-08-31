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

    // AC-4.3.10 / AC-4.4.7 / AC-4.5.2: a password change signs the account out
    // everywhere - browser sessions and app tokens alike. Every route to a new
    // password (profile screen, app endpoint, OTP reset, administrator reset)
    // passes through update_internal_user_password(), which fires this, so one
    // observer covers all four - including the two that live in core files.
    [
        'eventname' => '\core\event\user_password_updated',
        'callback'  => '\local_academy\observer::user_password_updated',
    ],

    // AC-4.24.4: blocking takes effect immediately. Core destroys the browser
    // sessions of an account it suspends but keeps its web-service tokens alive,
    // which would leave a blocked learner still signed in on the app.
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => '\local_academy\observer::user_updated',
    ],
];
