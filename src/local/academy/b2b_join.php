<?php
// B2B invitation landing page (US-B2B-1-3). Validates the link, then registers/logs the user in and
// creates a membership request. The link itself never grants access — it only creates a membership.

require('../../config.php');
require_once($CFG->dirroot . '/local/academy/lib.php');

$token = required_param('t', PARAM_RAW_TRIMMED);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/academy/b2b_join.php', array('t' => $token)));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('b2b_join_title', 'local_academy'));

// Not logged in → send to login, returning here afterwards so the membership is created post-login.
// Moodle returns the user to $SESSION->wantsurl after a successful login/registration.
if (!isloggedin() || isguestuser()) {
    $SESSION->wantsurl = (new moodle_url('/local/academy/b2b_join.php', array('t' => $token)))->out(false);
    redirect(new moodle_url('/login/index.php'), get_string('b2b_join_login', 'local_academy'));
}

// Validate the invitation up front so an invalid/expired/revoked link shows a clean message.
$valid = true;
$errormsg = '';
try {
    \local_academy\b2b_manager::validate_invitation($token);
} catch (\moodle_exception $e) {
    $valid = false;
    $errormsg = $e->getMessage();
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('b2b_join_title', 'local_academy'));

if (!$valid) {
    echo $OUTPUT->notification($errormsg, 'error');
    echo $OUTPUT->footer();
    exit;
}

// Create (or fetch) the membership for the logged-in user. Idempotent — reopening won't duplicate.
try {
    $result = \local_academy\b2b_manager::join($token, $USER->id);
    $statuskey = 'b2b_join_' . $result['status']; // b2b_join_pending | b2b_join_approved | ...
    $notifytype = ($result['status'] === \local_academy\b2b_manager::M_APPROVED) ? 'success' : 'info';
    $text = get_string(get_string_manager()->string_exists($statuskey, 'local_academy')
        ? $statuskey : 'b2b_join_pending', 'local_academy');
    echo $OUTPUT->notification($text, $notifytype);
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/academy/student.php'),
            get_string('b2b_join_goto', 'local_academy'), array('class' => 'btn btn-primary')),
        'mt-3'
    );
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification($e->getMessage(), 'error');
}

echo $OUTPUT->footer();
