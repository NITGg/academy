<?php
// Temporary end-to-end test for the combinable groups. Deleted after the run.
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

use core_message\api as msg;
use local_msgrules\rules;
use local_msgrules\sync;

global $DB;

$fails = 0;
function check($label, $got, $want) {
    global $fails;
    $ok = ($got === $want);
    if (!$ok) {
        $fails++;
    }
    printf("  [%s] %-46s (got %s, want %s)\n", $ok ? 'PASS' : 'FAIL', $label,
        var_export($got, true), var_export($want, true));
}

$origallusers = $CFG->messagingallusers ?? 0;
set_config('messagingallusers', 1);
$CFG->messagingallusers = 1;

echo "== Fixtures ==\n";
$course = create_course((object) ['fullname' => 'ZZ Course', 'shortname' => 'zzcourse', 'category' => 1]);
$mk = function ($n) {
    $u = new stdClass();
    $u->username = $n; $u->password = 'Zz!' . random_string(10) . '9';
    $u->firstname = 'ZZ'; $u->lastname = $n; $u->email = $n . '@example.invalid';
    $u->confirmed = 1; $u->mnethostid = 1; $u->auth = 'manual';
    return user_create_user($u, true, false);
};
$s1 = $mk('zz_s1');
$s2 = $mk('zz_s2');
$teacher = $mk('zz_t1');
$outsider = $mk('zz_out');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$manual->enrol_user($instance, $s1, $DB->get_field('role', 'id', ['shortname' => 'student']));
$manual->enrol_user($instance, $s2, $DB->get_field('role', 'id', ['shortname' => 'student']));
$manual->enrol_user($instance, $teacher, $DB->get_field('role', 'id', ['shortname' => 'editingteacher']));
$admin = (int) explode(',', $CFG->siteadmins)[0];
echo "  s1=$s1 s2=$s2 teacher=$teacher outsider=$outsider admin=$admin\n";

set_config('enabled', 1, 'local_msgrules');

$apply = function ($label, $mode) use ($course) {
    rules::set_course_mode($course->id, $mode);
    $r = sync::rebuild();
    echo "\n== $label ==\n";
    echo '  ' . rules::describe($mode) . " -> {$r['students']} restricted, {$r['added']} added, {$r['removed']} removed\n";
};

// ---------------------------------------------------------------- teachers only
$apply('Teachers only', rules::ALLOW_TEACHERS);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('s1 -> s2', msg::can_send_message($s2, $s1), false);
check('s1 -> admin', msg::can_send_message($admin, $s1), false);
check('s1 -> outsider', msg::can_send_message($outsider, $s1), false);
check('admin -> s1 (admins never restricted)', msg::can_send_message($s1, $admin), true);

// ---------------------------------------------------------------- admins only  (NEW)
$apply('Site administrators only', rules::ALLOW_ADMINS);
check('s1 -> admin', msg::can_send_message($admin, $s1), true);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), false);
check('s1 -> s2', msg::can_send_message($s2, $s1), false);

// ---------------------------------------------------------------- students only
$apply('Fellow students only', rules::ALLOW_PEERS);
check('s1 -> s2', msg::can_send_message($s2, $s1), true);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), false);
check('s1 -> admin', msg::can_send_message($admin, $s1), false);

// ---------------------------------------------------------------- two at once  (NEW)
$apply('Teachers AND administrators', rules::ALLOW_TEACHERS | rules::ALLOW_ADMINS);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('s1 -> admin', msg::can_send_message($admin, $s1), true);
check('s1 -> s2 (peers not ticked)', msg::can_send_message($s2, $s1), false);

// ---------------------------------------------------------------- nobody  (NEW)
$apply('Nobody', rules::ALLOW_NOBODY);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), false);
check('s1 -> admin', msg::can_send_message($admin, $s1), false);
check('s1 -> s2', msg::can_send_message($s2, $s1), false);
check('s1 -> s1 (self-conversation)', msg::can_send_message($s1, $s1), true);
check('teacher -> s1 (teachers never restricted)', msg::can_send_message($s1, $teacher), true);
check('admin -> s1', msg::can_send_message($s1, $admin), true);

// ---------------------------------------------------------------- all three
$apply('All three ticked', rules::ALLOW_TEACHERS | rules::ALLOW_ADMINS | rules::ALLOW_PEERS);
check('s1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('s1 -> admin', msg::can_send_message($admin, $s1), true);
check('s1 -> s2', msg::can_send_message($s2, $s1), true);
check('s1 -> outsider (still off the course)', msg::can_send_message($outsider, $s1), false);

// ---------------------------------------------------------------- open
$apply('No restriction', rules::OPEN);
check('s1 -> outsider', msg::can_send_message($outsider, $s1), true);
check('managed rows cleared', $DB->count_records('local_msgrules_managed'), 0);

// ---------------------------------------------------------------- describe()
echo "\n== describe() ==\n";
foreach ([rules::OPEN, rules::ALLOW_NOBODY, rules::ALLOW_ADMINS,
          rules::ALLOW_TEACHERS | rules::ALLOW_ADMINS] as $m) {
    printf("  %3d => %s\n", $m, rules::describe($m));
}

// ---------------------------------------------------------------- off
echo "\n== Switching off ==\n";
rules::set_course_mode($course->id, rules::ALLOW_NOBODY);
sync::rebuild();
set_config('enabled', 0, 'local_msgrules');
sync::rebuild();
check('s1 -> outsider', msg::can_send_message($outsider, $s1), true);
check('no managed rows', $DB->count_records('local_msgrules_managed'), 0);
check('no block rows', $DB->count_records('message_users_blocked'), 0);

echo "\n== Cleanup ==\n";
delete_course($course->id, false);
foreach ([$s1, $s2, $teacher, $outsider] as $id) {
    delete_user($DB->get_record('user', ['id' => $id]));
    $DB->delete_records('user', ['id' => $id]);
}
$DB->delete_records('local_msgrules_course');
$DB->delete_records('local_msgrules_managed');
unset_config('enabled', 'local_msgrules');
unset_config('defaultmode', 'local_msgrules');
set_config('messagingallusers', $origallusers);
echo "  done\n";

echo "\n" . ($fails ? "*** $fails FAILED ***\n" : "ALL CHECKS PASSED\n");
exit($fails ? 1 : 0);
