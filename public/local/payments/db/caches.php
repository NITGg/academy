<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'country_detection' => [
        'mode' => cache_store::MODE_APPLICATION,
        // Keys are IP addresses (dots, and colons for IPv6), which are not valid
        // "simple keys" — let Moodle hash them instead. Values are plain strings.
        'simpledata' => true,
        'ttl' => 86400, // 24 hours.
    ],
    // Payment methods a provider account has enabled (Fawaterk's getPaymentmethods).
    // Keyed by provider plugin name. Cached so picking a method doesn't add an
    // API round-trip to every checkout; it only changes when someone edits the
    // gateway account, so a short TTL is plenty.
    'provider_payment_methods' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'ttl' => 3600, // 1 hour.
    ],
];
