<?php
// TEMP — mint a mobile-service token for admin so we can curl the WS endpoints. Deleted after use.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');

$admin = get_admin();
$service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
$context = context_system::instance();

$existing = $DB->get_records('external_tokens', ['userid' => $admin->id, 'externalserviceid' => $service->id]);
$token = $existing ? reset($existing)->token
    : \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $admin->id, $context);

echo $token . "\n";
