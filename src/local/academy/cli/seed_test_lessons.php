<?php
// Academy — test data seeder for the lesson lifecycle.
//
// Creates teachers + students and drives the real lesson_manager APIs through every
// status so the UI (and the per-room access restrictions) can be tested with several
// unrelated teacher/student pairs.
//
// Run inside the app container:
//   docker exec academy_app php /var/www/html/local/academy/cli/seed_test_lessons.php
// Options:
//   --reset   delete previously-seeded lessons + rooms before seeding again
//   --help

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php'); // course_delete_module() for --reset

use local_academy\lesson_manager;
use local_academy\teacher_manager;
use local_academy\purchase_manager;
use local_academy\package_manager;
use local_academy\settings_manager;

list($options, $unrecognised) = cli_get_params(
    array('help' => false, 'reset' => false),
    array('h' => 'help')
);
if ($options['help']) {
    cli_writeln("Seed Academy lesson lifecycle test data.\n  --reset  remove prior seed data first\n");
    exit(0);
}

// Run as the main admin so room creation (create_module) and role assignment pass.
\core\session\manager::set_user(get_admin());
global $DB, $USER;

// Surface the exact DB/error detail while seeding.
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;

$PASSWORD   = 'Test@1234';
$SUBJECTS   = array('Mathematics', 'Physics', 'English');
$courseid   = (int) settings_manager::get('lessons_courseid');

// $CFG->mnethostid is empty in this CLI context — resolve the local mnet host id for user creation.
$MNETHOST = (int)($CFG->mnethostid ?? 0);
if (!$MNETHOST) { $MNETHOST = (int) get_config('core', 'mnet_localhost_id'); }
if (!$MNETHOST) { $MNETHOST = (int) $DB->get_field('mnet_host', 'id', array('wwwroot' => $CFG->wwwroot)); }
if (!$MNETHOST) { $MNETHOST = 1; }
$CFG->mnethostid = $MNETHOST;

if (!$DB->record_exists('course', array('id' => $courseid))) {
    cli_error("lessons_courseid ($courseid) does not exist. Set it in Academy settings first.");
}
cli_heading("Academy seed — lessons course id = $courseid");

// ── helpers ───────────────────────────────────────────────────────────────

/** Create the user if missing (matched by username); return its id. */
function seed_user($username, $first, $last, $isteacher) {
    global $DB, $CFG, $PASSWORD, $SUBJECTS;
    $existing = $DB->get_record('user', array('username' => $username, 'deleted' => 0));
    if ($existing) {
        $uid = (int) $existing->id;
    } else {
        $u = new stdClass();
        $u->auth         = 'manual';
        $u->confirmed    = 1;
        $u->mnethostid   = $GLOBALS['MNETHOST'];
        $u->username     = $username;
        $u->password     = $PASSWORD;
        $u->firstname    = $first;
        $u->lastname     = $last;
        $u->email        = $username . '@example.com';
        $uid = user_create_user($u, true, false);
    }
    if ($isteacher) {
        // is_teacher() looks for a teacher/editingteacher role assignment anywhere.
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));
        $syscontext = context_system::instance();
        if ($roleid && !$DB->record_exists('role_assignments',
                array('roleid' => $roleid, 'userid' => $uid, 'contextid' => $syscontext->id))) {
            role_assign($roleid, $uid, $syscontext->id);
        }
        // Profile + subjects so request_lesson()'s subject check passes.
        teacher_manager::update_profile($uid, array(
            'headline' => 'Test teacher',
            'bio'      => 'Seeded teacher',
            'available' => 1,
            'subjects' => array_map(function ($s) { return array('subject' => $s); }, $SUBJECTS),
        ));
    }
    return $uid;
}

/** Ensure the student holds an active package with Flex. */
function seed_flex($studentid, $packageid) {
    if (!purchase_manager::get_active_purchase($studentid)) {
        purchase_manager::assign_package($GLOBALS['USER']->id, $studentid, $packageid, 0, 'offline', 'seed');
    }
}

/** Move a lesson's scheduled time so window-gated transitions (start/complete/absence) pass. */
function nudge_time($lessonid, $field, $seconds) {
    global $DB;
    $DB->set_field('academy_lessons', $field, time() + $seconds, array('id' => $lessonid));
}

$future  = function ($hours = 2) { return time() + $hours * 3600; };

// ── optional reset ──────────────────────────────────────────────────────────
if ($options['reset']) {
    $like = $DB->sql_like('username', ':u');
    $seedusers = $DB->get_records_select('user', "$like AND deleted = 0", array('u' => 'seed\_%'));
    $ids = array_keys($seedusers);
    if ($ids) {
        $lessons = array();
        foreach ($ids as $uid) {
            foreach ($DB->get_records_select('academy_lessons',
                    'studentid = ? OR teacherid = ?', array($uid, $uid)) as $l) {
                $lessons[$l->id] = $l;
            }
        }
        foreach ($lessons as $l) {
            if (!empty($l->cmid)) {
                // Remove the room activity (and its session rows).
                try { course_delete_module((int)$l->cmid); } catch (\Throwable $e) { /* ignore */ }
            }
            if (!empty($l->sessionid)) {
                $DB->delete_records('academy_session_students', array('sessionid' => $l->sessionid));
                $DB->delete_records('academy_session_attendance', array('sessionid' => $l->sessionid));
                $DB->delete_records('academy_live_sessions', array('id' => $l->sessionid));
            }
            $DB->delete_records('academy_lesson_proposals', array('lessonid' => $l->id));
            $DB->delete_records('academy_lessons', array('id' => $l->id));
        }
        // Drop the per-lesson groups in the lessons course.
        $DB->delete_records_select('groups',
            'courseid = :cid AND ' . $DB->sql_like('idnumber', ':p'),
            array('cid' => $courseid, 'p' => 'academy\_lesson\_%'));
        rebuild_course_cache($courseid, true);
        cli_writeln('Reset: removed ' . count($lessons) . ' prior seed lesson(s) and their rooms.');
    }
}

// ── package + users ──────────────────────────────────────────────────────────
$packages = package_manager::get_packages(package_manager::STATUS_ACTIVE);
if ($packages) {
    $packageid = (int) $packages[0]->id;
} else {
    $packageid = package_manager::create_package(array(
        'name' => 'Seed Package (50 Flex)', 'flex_count' => 50, 'price' => 1000,
        'expiration_days' => 365, 'active' => 1,
    ), $USER->id);
}

$teachers = array();
for ($i = 1; $i <= 3; $i++) {
    $teachers[$i] = seed_user("seed_t$i", "Teacher$i", 'Seed', true);
}
$students = array();
for ($i = 1; $i <= 6; $i++) {
    $students[$i] = seed_user("seed_s$i", "Student$i", 'Seed', false);
    seed_flex($students[$i], $packageid);
}
cli_writeln('Users ready: 3 teachers (seed_t1..3), 6 students (seed_s1..6). Password: ' . $PASSWORD);

// ── drive the lifecycle ──────────────────────────────────────────────────────
$results = array();
// Note: request_lesson() takes ($studentid, $teacherid, ...); call sites below pass teacher first.
$mk = function ($teacherid, $studentid, $subject, $hours) use ($future) {
    return lesson_manager::request_lesson($studentid, $teacherid, $subject,
        $future($hours), 'Seeded lesson note');
};

// 1. pending — request only.
$l = $mk($teachers[1], $students[1], 'Mathematics', 2);
$results['pending'] = $l['id'];

// 2. waiting_student — teacher suggested another time.
$l = $mk($teachers[1], $students[2], 'Physics', 2);
lesson_manager::teacher_respond($teachers[1], $l['id'], 'suggest', array('suggested_time' => $future(5)));
$results['waiting_student'] = $l['id'];

// 3. waiting_teacher — teacher suggested, then student counter-suggested.
$l = $mk($teachers[2], $students[3], 'English', 2);
lesson_manager::teacher_respond($teachers[2], $l['id'], 'suggest', array('suggested_time' => $future(5)));
lesson_manager::student_respond($students[3], $l['id'], 'suggest', array('suggested_time' => $future(6)));
$results['waiting_teacher'] = $l['id'];

// 4. rejected — teacher rejected a pending request.
$l = $mk($teachers[2], $students[4], 'Mathematics', 2);
lesson_manager::teacher_respond($teachers[2], $l['id'], 'reject', array('reject_reason' => 'Seed reject'));
$results['rejected'] = $l['id'];

// 5. confirmed — teacher accepted.
$l = $mk($teachers[3], $students[5], 'Physics', 2);
lesson_manager::teacher_respond($teachers[3], $l['id'], 'accept');
$results['confirmed'] = $l['id'];

// 6. cancelled (by student) — confirm then student cancels.
$l = $mk($teachers[3], $students[6], 'English', 2);
lesson_manager::teacher_respond($teachers[3], $l['id'], 'accept');
lesson_manager::cancel_as_student($students[6], $l['id'], 'Seed student cancel');
$results['cancelled_student'] = $l['id'];

// 7. cancelled (by teacher) — confirm then teacher cancels.
$l = $mk($teachers[1], $students[3], 'Mathematics', 2);
lesson_manager::teacher_respond($teachers[1], $l['id'], 'accept');
lesson_manager::cancel_as_teacher($teachers[1], $l['id'], 'Seed teacher cancel');
$results['cancelled_teacher'] = $l['id'];

// 8. student_absent — confirm, move start into the past, report.
$l = $mk($teachers[2], $students[1], 'Physics', 2);
lesson_manager::teacher_respond($teachers[2], $l['id'], 'accept');
nudge_time($l['id'], 'confirmed_time', -20 * 60);
lesson_manager::report_student_absent($teachers[2], $l['id']);
$results['student_absent'] = $l['id'];

// 9. teacher_absent — confirm, move start into the past, student reports.
$l = $mk($teachers[3], $students[2], 'English', 2);
lesson_manager::teacher_respond($teachers[3], $l['id'], 'accept');
nudge_time($l['id'], 'confirmed_time', -20 * 60);
lesson_manager::report_teacher_absent($students[2], $l['id']);
$results['teacher_absent'] = $l['id'];

// 10. in_progress (Room A) — T1 + S4. Confirm, nudge to now, start → creates room.
$l = $mk($teachers[1], $students[4], 'Mathematics', 2);
lesson_manager::teacher_respond($teachers[1], $l['id'], 'accept');
nudge_time($l['id'], 'confirmed_time', -5 * 60);
lesson_manager::start_lesson($teachers[1], $l['id']);
$results['in_progress_A (T1+S4)'] = $l['id'];

// 11. in_progress (Room B) — T2 + S5. A different pair, to test cross-visibility.
$l = $mk($teachers[2], $students[5], 'Physics', 2);
lesson_manager::teacher_respond($teachers[2], $l['id'], 'accept');
nudge_time($l['id'], 'confirmed_time', -5 * 60);
lesson_manager::start_lesson($teachers[2], $l['id']);
$results['in_progress_B (T2+S5)'] = $l['id'];

// 12. completed — start, push start far enough back, complete (consumes Flex + revenue).
$l = $mk($teachers[3], $students[1], 'English', 2);
lesson_manager::teacher_respond($teachers[3], $l['id'], 'accept');
nudge_time($l['id'], 'confirmed_time', -5 * 60);
lesson_manager::start_lesson($teachers[3], $l['id']);
nudge_time($l['id'], 'actual_start', -200 * 60);
lesson_manager::complete_lesson($teachers[3], $l['id']);
$results['completed'] = $l['id'];

// ── report ───────────────────────────────────────────────────────────────────
cli_heading('Seeded lessons');
foreach ($results as $status => $lessonid) {
    $row = $DB->get_record('academy_lessons', array('id' => $lessonid));
    $line = sprintf('  %-26s lesson #%-4d status=%-16s', $status, $lessonid, $row->status);
    if ((int)$row->cmid > 0) {
        $line .= ' room: ' . $CFG->wwwroot . '/mod/jitsi/view.php?id=' . (int)$row->cmid;
    }
    cli_writeln($line);
}

cli_heading('How to test the room restriction');
cli_writeln("Course page: {$CFG->wwwroot}/course/view.php?id={$courseid}");
cli_writeln('Log in (NOT as admin — admin sees everything) and check the two in-progress rooms:');
cli_writeln('  seed_t1 / seed_s4  -> should see Room A only');
cli_writeln('  seed_t2 / seed_s5  -> should see Room B only');
cli_writeln('  any other teacher/student -> should see neither');
cli_writeln('All passwords: ' . $PASSWORD);
cli_writeln("\nDone.");
