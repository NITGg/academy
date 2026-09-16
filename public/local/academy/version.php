<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_academy';
$plugin->version   = 2026091600; // Site opens in Arabic: default language ar, language autodetect off (only moves the stock 'en').
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0-quiz';

// The certificates web service renders in the caller's language via
// \local_nit_core\helper\lang, which must therefore be installed.
$plugin->dependencies = [
    'local_nit_core' => 2026080404,
];
