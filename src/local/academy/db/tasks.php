<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled tasks for local_academy.
 */
$tasks = array(
    array(
        'classname' => 'local_academy\task\expiry_reminder_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '8',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ),
    array(
        'classname' => 'local_academy\task\subscription_expiry_task',
        'blocking'  => 0,
        'minute'    => '5',
        'hour'      => '8',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ),
);
