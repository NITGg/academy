<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_payments';
$plugin->version   = 2026083122;   // Staff refund from the payments list; fix eaten escapes in history.
$plugin->requires  = 2024100700; // Moodle 4.5+
$plugin->supported = [405, 502];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
