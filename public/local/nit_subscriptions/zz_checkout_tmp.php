<?php
// TEMP — reproduce create_subscription_checkout error with full detail. Deleted after use.
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');

\core\session\manager::set_user(get_admin());

global $DB;
echo "subscriptions in DB: ";
$subs = $DB->get_records('nit_subscription', null, 'id', 'id,name,status,price');
foreach ($subs as $s) { echo "#{$s->id}({$s->status},{$s->price}) "; }
echo "\n";
echo "payment providers: ";
foreach ($DB->get_records('local_payments_providers') as $p) {
    echo "{$p->name}(enabled={$p->enabled}) ";
}
echo "\n";

$firstid = $subs ? (int) reset($subs)->id : 1;
echo "trying checkout for subscriptionid={$firstid}...\n";
try {
    $r = \local_nit_subscriptions\external\create_subscription_checkout::execute($firstid);
    echo "OK: " . json_encode($r) . "\n";
} catch (\Throwable $e) {
    echo "THREW: " . get_class($e) . "\n";
    echo "  message:   " . $e->getMessage() . "\n";
    if ($e instanceof \moodle_exception) {
        echo "  errorcode: " . $e->errorcode . "\n";
        echo "  debuginfo: " . ($e->debuginfo ?? '') . "\n";
    }
}
