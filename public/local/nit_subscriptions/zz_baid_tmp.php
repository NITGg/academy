<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../config.php');
\core\session\manager::set_user(get_admin());
foreach ([['checkout', 999], ['preview', 999]] as [$what, $id]) {
    try {
        if ($what === 'checkout') {
            \local_nit_subscriptions\external\create_subscription_checkout::execute($id);
        } else {
            \local_nit_commerce\external\preview_discount::execute('subscription', $id, '');
        }
        echo "$what($id): OK (no throw)\n";
    } catch (\Throwable $e) {
        echo "$what($id): " . get_class($e) . " | msg='" . $e->getMessage() . "'";
        if ($e instanceof \moodle_exception) { echo " | debug='" . ($e->debuginfo ?? '') . "'"; }
        echo "\n";
    }
}
