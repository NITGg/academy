<?php
// Temporary end-to-end test for the per-course model. Deleted after the run.
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
    printf("  [%s] %-52s (got %s, want %s)\n", $ok ? 'PASS' : 'FAIL', $label,
        var_export($got, true), var_export($want, true));
}

// Core's own default (only people I share a course with) would deny most of these pairs on
// its own and hide what the plugin is doing. Restored at the end.
$origallusers = $CFG->messagingallusers ?? 0;
set_config('messagingallusers', 1);
$CFG->messagingallusers = 1;

echo "== Fixtures ==\n";
$course = create_course((object) [
    'fullname' => 'ZZ Test Course', 'shortname' => 'zztestcourse', 'category' => 1,
]);
$mk = function ($n) {
    $u = new stdClass();
    $u->username = $n; $u->password = 'Zz!' . random_string(10) . '9';
    $u->firstname = 'ZZ'; $u->lastname = $n; $u->email = $n . '@example.invalid';
    $u->confirmed = 1; $u->mnethostid = 1; $u->auth = 'manual';
    return user_create_user($u, true, false);
};
$s1 = $mk('zz_student1');
$s2 = $mk('zz_student2');
$teacher = $mk('zz_teacher');
$outsider = $mk('zz_outsider');

$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$teacherrole = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$manual->enrol_user($instance, $s1, $studentrole);
$manual->enrol_user($instance, $s2, $studentrole);
$manual->enrol_user($instance, $teacher, $teacherrole);

echo "  course {$course->id}; student1=$s1 student2=$s2 teacher=$teacher outsider=$outsider\n";

set_config('enabled', 1, 'local_msgrules');

$scenario = function ($label, $mode) use ($course) {
    rules::set_course_mode($course->id, $mode);
    $r = sync::rebuild();
    echo "\n== $label ==\n";
    echo "  applied: {$r['students']} restricted students, {$r['added']} added, {$r['removed']} removed\n";
};

// ---------------------------------------------------------------- no restriction
$scenario('Mode: no restriction', rules::MODE_OPEN);
check('student1 -> student2', msg::can_send_message($s2, $s1), true);
check('student1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('student1 -> outsider', msg::can_send_message($outsider, $s1), true);

// ---------------------------------------------------------------- peers only
$scenario('Mode: students may message each other only', rules::MODE_PEERS);
check('student1 -> student2  (classmate)', msg::can_send_message($s2, $s1), true);
check('student1 -> teacher   (not allowed by this mode)', msg::can_send_message($teacher, $s1), false);
check('student1 -> outsider  (not on the course)', msg::can_send_message($outsider, $s1), false);
check('teacher  -> student1  (teachers are never restricted)', msg::can_send_message($s1, $teacher), true);
check('student1 -> student1  (self-conversation)', msg::can_send_message($s1, $s1), true);

// ---------------------------------------------------------------- peers + teachers
$scenario('Mode: students may message each other and the teachers', rules::MODE_PEERS_AND_TEACHERS);
check('student1 -> student2', msg::can_send_message($s2, $s1), true);
check('student1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('student1 -> outsider  (still off the course)', msg::can_send_message($outsider, $s1), false);

// ---------------------------------------------------------------- teachers only
$scenario('Mode: students may message the teachers only', rules::MODE_TEACHERS_ONLY);
check('student1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('student1 -> student2  (no student-to-student)', msg::can_send_message($s2, $s1), false);
check('teacher  -> student1', msg::can_send_message($s1, $teacher), true);

// ---------------------------------------------------------------- admin exemption
echo "\n== Admin exemption ==\n";
$adminid = (int) explode(',', $CFG->siteadmins)[0];
check('admin    -> student1', msg::can_send_message($s1, $adminid), true);
check('student1 -> admin', msg::can_send_message($adminid, $s1), true);

// ---------------------------------------------------------------- self-service unblock
echo "\n== A student cannot lift the restriction on themselves ==\n";
msg::unblock_user($s2, $s1);
check('student1 -> student2 after student2 unblocked them', msg::can_send_message($s2, $s1), false);

// ---------------------------------------------------------------- site default
echo "\n== Site default applies to courses with no setting of their own ==\n";
rules::set_course_mode($course->id, null);
set_config('defaultmode', rules::MODE_TEACHERS_ONLY, 'local_msgrules');
sync::rebuild();
check('student1 -> student2 (default is teachers only)', msg::can_send_message($s2, $s1), false);
check('student1 -> teacher', msg::can_send_message($teacher, $s1), true);

echo "\n== A course override beats the site default ==\n";
rules::set_course_mode($course->id, rules::MODE_OPEN);
sync::rebuild();
check('student1 -> student2 (course opened again)', msg::can_send_message($s2, $s1), true);
check('student1 -> outsider (nothing restricts them now)', msg::can_send_message($outsider, $s1), true);

// ---------------------------------------------------------------- off
echo "\n== Switching off restores everything ==\n";
set_config('defaultmode', rules::MODE_OPEN, 'local_msgrules');
set_config('enabled', 0, 'local_msgrules');
$r = sync::rebuild();
check('student1 -> outsider', msg::can_send_message($outsider, $s1), true);
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
