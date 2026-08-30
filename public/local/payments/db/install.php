<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_install() {
    global $DB;

    // Seed Kashier provider record.
    if (!$DB->record_exists('local_payments_providers', ['name' => 'kashier'])) {
        $DB->insert_record('local_payments_providers', (object) [
            'name' => 'kashier',
            'display_name' => 'Kashier',
            'plugin_name' => 'paymentprovider_kashier',
            'enabled' => 0,
            'priority' => 100,
            'supported_countries' => json_encode(['EG', 'SA', 'AE', 'KW', 'BH', 'QA', 'OM']),
            'supported_currencies' => json_encode(['EGP', 'USD', 'EUR', 'GBP', 'SAR', 'AED']),
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    // Seed Fawaterk provider record.
    local_payments_seed_fawaterk_provider();

    return true;
}

/**
 * Register the Fawaterk provider row if it isn't there yet.
 *
 * Disabled on creation — an admin enables it from Manage providers once the
 * vendor key is entered. Called from both install and upgrade so existing
 * sites pick it up too.
 */
function local_payments_seed_fawaterk_provider() {
    global $DB;

    if ($DB->record_exists('local_payments_providers', ['name' => 'fawaterk'])) {
        return;
    }

    $DB->insert_record('local_payments_providers', (object) [
        'name' => 'fawaterk',
        'display_name' => 'Fawaterk',
        'plugin_name' => 'paymentprovider_fawaterk',
        'enabled' => 0,
        // Higher number = lower priority in manager::get_provider(), so Kashier
        // (100) stays the default pick until an admin reorders them.
        'priority' => 110,
        'supported_countries' => json_encode(['EG', 'SA', 'AE']),
        'supported_currencies' => json_encode(['EGP', 'USD', 'SAR', 'AED']),
        'timecreated' => time(),
        'timemodified' => time(),
    ]);
}
