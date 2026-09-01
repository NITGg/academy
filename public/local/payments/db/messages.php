<?php
defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Tells a buyer what happened to a refund they asked for.
    'refund_decision' => [
        'capability' => 'local/payments:viewownhistory',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    'payment_confirmation' => [
        'capability' => 'local/payments:purchasecourse',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
