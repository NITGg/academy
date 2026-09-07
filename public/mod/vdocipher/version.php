<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_vdocipher';
$plugin->version   = 2026090700;
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';
$plugin->dependencies = [
    'local_vdocipher' => ANY_VERSION, // API client, mapping table, playback OTP
    'local_nit_ai'    => ANY_VERSION, // Transcript store and the video assistant
];
