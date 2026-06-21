<?php
require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('googlemeet', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$googlemeet = $DB->get_record('googlemeet', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/googlemeet:view', $context);

$is_teacher = has_capability('mod/googlemeet:editrecording', $context);

// Check session access for students.
$linked_session = $DB->get_record('academy_live_sessions', array('googlemeetid' => $googlemeet->id));
if ($linked_session && !$is_teacher) {
    $allowed = $DB->record_exists('academy_session_students', array(
        'sessionid' => $linked_session->id,
        'userid' => $USER->id
    ));
    if (!$allowed) {
        throw new moodle_exception('nopermissions', 'error', '', 'join this session');
    }

    $now = time();
    if ($now < $linked_session->start_time - 1800 || $now > $linked_session->start_time + ($linked_session->duration * 60)) {
        throw new moodle_exception('nopermissions', 'error', '', 'session not available');
    }

    // Record attendance.
    if (!$DB->record_exists('academy_session_attendance', array('sessionid' => $linked_session->id, 'userid' => $USER->id))) {
        $att = new stdClass();
        $att->sessionid = $linked_session->id;
        $att->userid = $USER->id;
        $att->joined_at = $now;
        $att->duration_seconds = 0;
        $DB->insert_record('academy_session_attendance', $att);
    }
}

// Server-side redirect — the Meet URL never appears in page source.
header('Location: ' . $googlemeet->url);
exit;
