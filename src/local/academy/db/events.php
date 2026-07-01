<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 *
 * Pushes a realtime nudge to the recipient whenever any notification is sent, so the navbar bell
 * can chime + refresh live (see {@see \local_academy\observer} and {@see \local_academy\realtime}).
 */
$observers = array(
    array(
        'eventname' => '\core\event\notification_sent',
        'callback'  => '\local_academy\observer::notification_sent',
    ),
);
