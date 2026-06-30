<?php
// Mint (or reuse) a web-service token for each seeded user so api.php can be tested over HTTP.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
global $DB;

// Pick an enabled external service (prefer the mobile app service).
$service = $DB->get_record('external_services', array('shortname' => 'moodle_mobile_app', 'enabled' => 1));
if (!$service) {
    $service = $DB->get_record_select('external_services', 'enabled = 1', null, '*', IGNORE_MULTIPLE);
}
if (!$service) {
    fwrite(STDERR, "No enabled external service found\n");
    exit(1);
}

$ctx = context_system::instance();
$usernames = array('seed_t1','seed_t2','seed_t3','seed_s1','seed_s4','seed_s5','seed_s6');
foreach ($usernames as $uname) {
    $u = $DB->get_record('user', array('username' => $uname, 'deleted' => 0));
    if (!$u) { continue; }
    $existing = $DB->get_records('external_tokens',
        array('userid' => $u->id, 'externalserviceid' => $service->id), 'id DESC', '*', 0, 1);
    if ($existing) {
        $tok = reset($existing)->token;
    } else {
        $tok = external_generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $u->id, $ctx);
    }
    echo "$uname $tok\n";
}
