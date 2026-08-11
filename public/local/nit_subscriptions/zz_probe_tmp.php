<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');
$admin = get_admin();
$service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);
$existing = $DB->get_records('external_tokens', ['userid' => $admin->id, 'externalserviceid' => $service->id]);
$tok = $existing ? reset($existing)->token
    : \core_external\util::generate_token(EXTERNAL_TOKEN_PERMANENT, $service, $admin->id, context_system::instance());
echo "TOKEN=$tok\n";
// A course with a pricing rule (for price/access/checkout tests).
$priced = $DB->get_records_sql("SELECT DISTINCT courseid FROM {local_payments_course_prices} LIMIT 5");
echo "PRICED_COURSES=" . implode(',', array_map(fn($r)=>$r->courseid, $priced)) . "\n";
// Any transaction (for invoice/verify tests).
$txn = $DB->get_records_sql("SELECT id, order_id, status, courseid FROM {local_payments_transactions} ORDER BY id DESC LIMIT 3");
foreach ($txn as $t) { echo "TXN id={$t->id} order={$t->order_id} status={$t->status} course={$t->courseid}\n"; }
// registered local_payments functions
$fns = $DB->get_records_sql("SELECT name FROM {external_functions} WHERE name LIKE 'local_payments%' ORDER BY name");
echo "REGISTERED=" . implode(',', array_map(fn($r)=>$r->name, $fns)) . "\n";
