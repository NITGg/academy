<?php
// Temporary test: saving the SETTINGS PAGE must actually apply the restriction.
// This is the path that was silently doing nothing. Deleted after the run.
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

use core_message\api as msg;
use local_msgrules\rules;

global $DB;

$fails = 0;
function check($label, $got, $want) {
    global $fails;
    $ok = ($got === $want);
    if (!$ok) {
        $fails++;
    }
    printf("  [%s] %-50s (got %s, want %s)\n", $ok ? 'PASS' : 'FAIL', $label,
        var_export($got, true), var_export($want, true));
}

\core\session\manager::set_user(get_admin());

$origallusers = $CFG->messagingallusers ?? 0;
set_config('messagingallusers', 1);
$CFG->messagingallusers = 1;

// Fixtures: one course, two students, one teacher.
$course = create_course((object) ['fullname' => 'ZZ React', 'shortname' => 'zzreact', 'category' => 1]);
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
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$manual = enrol_get_plugin('manual');
$manual->enrol_user($instance, $s1, $DB->get_field('role', 'id', ['shortname' => 'student']));
$manual->enrol_user($instance, $s2, $DB->get_field('role', 'id', ['shortname' => 'student']));
$manual->enrol_user($instance, $teacher, $DB->get_field('role', 'id', ['shortname' => 'editingteacher']));

echo "== Before ==\n";
check('student1 -> student2 (nothing set yet)', msg::can_send_message($s2, $s1), true);

echo "\n== Saving the settings page, exactly as the browser does ==\n";
// This is the real path: admin_write_settings() writes each value and then calls
// post_write_settings(), which is where the callback either fires or is skipped.
admin_get_root(true, true);
$result = admin_write_settings([
    's_local_msgrules_defaultmode' => rules::MODE_TEACHERS_ONLY,
    's_local_msgrules_enabled'     => 1,
    's_local_msgrules_maxusers'    => 2000,
]);
printf("  settings written, %d unsaved\n", count($result));
printf("  defaultmode now: %s\n", rules::get_modes()[rules::get_default_mode()]);
printf("  enabled now: %s\n", rules::is_enabled() ? 'yes' : 'no');
printf("  block rows the plugin owns: %d\n", \local_msgrules\sync::count_managed());

echo "\n== The restriction must be live with no further action ==\n";
check('student1 -> student2 (teachers only)', msg::can_send_message($s2, $s1), false);
check('student1 -> teacher', msg::can_send_message($teacher, $s1), true);
check('teacher -> student1', msg::can_send_message($s1, $teacher), true);

echo "\n== And turning it back off from the same page restores it ==\n";
admin_write_settings(['s_local_msgrules_enabled' => 0]);
check('student1 -> student2', msg::can_send_message($s2, $s1), true);
check('no rows left', $DB->count_records('local_msgrules_managed'), 0);

echo "\n== Cleanup ==\n";
delete_course($course->id, false);
foreach ([$s1, $s2, $teacher] as $id) {
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
