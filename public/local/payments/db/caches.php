<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'country_detection' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 86400, // 24 hours.
    ],
];
