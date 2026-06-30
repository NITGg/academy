<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Message providers for the Academy Flex platform.
 *
 * `lessonnotification` carries every teacher/student lesson-lifecycle notification
 * (request, accept/reject/suggest, start/complete, absence, cancel, time update).
 * Defaults enable both the popup processor (in-app bell on the web + the
 * `get_notifications` mobile pull, which reads {notifications}) and email.
 * See {@see \local_academy\notification_manager}.
 */
$messageproviders = array(

    'lessonnotification' => array(
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF,
        ),
    ),
);
