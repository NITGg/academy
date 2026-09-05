<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/config.php');
global $DB, $CFG;
use local_nit_emails\channels;

$CFG->noemailever = true;             // email_to_user() announces itself instead of sending.
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

$admin = get_admin();
$c = 'local_payments'; $n = 'payment_confirmation';

function fire($admin, $c, $n) {
    global $DB;
    $m = new \core\message\message();
    $m->component = $c;
    $m->name = $n;
    $m->userfrom = \core_user::get_noreply_user();
    $m->userto = $admin;
    $m->subject = 'delivery probe';
    $m->fullmessage = 'probe';
    $m->fullmessageformat = FORMAT_PLAIN;
    $m->fullmessagehtml = '<p>probe</p>';
    $m->smallmessage = 'probe';
    $m->notification = 1;

    ob_start();
    $id = message_send($m);
    $noise = ob_get_clean();

    $emailed = strpos($noise, 'noemailever') !== false;
    $popped  = $id && $DB->record_exists('message_popup_notifications', ['notificationid' => $id]);
    return ['id' => $id, 'email' => $emailed, 'popup' => $popped];
}

$scenarios = [
    'both ON'      => ['email' => true,  'popup' => true],
    'email OFF'    => ['email' => false, 'popup' => true],
    'popup OFF'    => ['email' => true,  'popup' => false],
    'both OFF'     => ['email' => false, 'popup' => false],
];

foreach ($scenarios as $label => $wanted) {
    channels::apply($c, $n, $wanted);
    $r = fire($admin, $c, $n);
    printf("%-12s asked email=%d popup=%d  ->  DELIVERED email=%s popup=%s  (msgid=%s)\n",
        $label, $wanted['email'], $wanted['popup'],
        $r['email'] ? 'YES' : 'no ', $r['popup'] ? 'YES' : 'no ', var_export($r['id'], true));
}

// Put it back the way it was.
channels::apply($c, $n, ['email' => true, 'popup' => true]);
$DB->delete_records_select('notifications', "subject = 'delivery probe'");
echo "restored\n";
