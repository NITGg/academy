<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');

use local_nit_emails\mailer;
use local_nit_emails\context_builder;
use local_nit_emails\templates;

global $DB;
$admin = get_admin();

$course = $DB->get_record_sql("SELECT * FROM {course} WHERE id > 1 ORDER BY id DESC", null, IGNORE_MULTIPLE);
if ($course) {
    echo "COURSE: {$course->id} {$course->fullname}\n";
    foreach (['en', 'ar'] as $l) {
        $old = force_current_language($l);
        $v = context_builder::course($admin, $course,
            (object) ['amount' => 999, 'currency' => 'EGP', 'order_id' => 'PAY-TEST-1']);
        $r = mailer::render(templates::EVENT_COURSE, $l, $v);
        force_current_language($old);
        echo "--- $l subject: {$r['subject']}\n";
        echo "    hours={$v['totalhours']} instructors={$v['instructors']}\n";
        echo "    content=" . substr(strip_tags($v['coursecontent']), 0, 120) . "\n";
        echo "    ilos=" . substr(strip_tags($v['ilos']), 0, 120) . "\n";
        if (preg_match_all('/\{[a-z_]+\}/', $r['html'], $m)) {
            echo "    UNRESOLVED: " . implode(',', array_unique($m[0])) . "\n";
        }
    }
} else {
    echo "no course found\n";
}

$sub = $DB->get_record_sql("SELECT * FROM {nit_subscription} ORDER BY id DESC", null, IGNORE_MULTIPLE);
if ($sub) {
    $purchase = (object) [
        'userid' => $admin->id, 'subscriptionid' => $sub->id, 'type' => 'normal', 'seats' => 0,
        'price_paid' => $sub->price, 'duration_days' => $sub->duration_days,
        'reference' => 'PAY-TEST-2', 'timeactivated' => time(),
        'expires_at' => time() + ($sub->duration_days * DAYSECS),
    ];
    foreach (['en', 'ar'] as $l) {
        $old = force_current_language($l);
        $v = context_builder::subscription($admin, $sub, $purchase);
        $r = mailer::render(templates::EVENT_SUBSCRIPTION, $l, $v);
        force_current_language($old);
        echo "SUB --- $l subject: {$r['subject']}\n";
        echo "    name={$v['subscriptionname']} days={$v['durationdays']} exp={$v['expirydate']}\n";
        if (preg_match_all('/\{[a-z_]+\}/', $r['html'], $m)) {
            echo "    UNRESOLVED: " . implode(',', array_unique($m[0])) . "\n";
        }
    }
} else {
    echo "no subscription found\n";
}
echo "OK2\n";
