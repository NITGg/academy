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

// Not logged in → offer BOTH log in and create-account, returning here afterwards so the membership
// is created post-auth. Moodle returns the user to $SESSION->wantsurl after a successful
// login/registration. (Auto-redirecting to /login/index.php only exposed the login form; an invited
// user without an account needs the register option too.)
if (!isloggedin() || isguestuser()) {
    $SESSION->wantsurl = (new moodle_url('/local/academy/b2b_join.php', array('t' => $token)))->out(false);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('b2b_join_title', 'local_academy'));
    echo $OUTPUT->notification(get_string('b2b_join_guest_intro', 'local_academy'), 'info');

    $buttons = html_writer::link(new moodle_url('/login/index.php'),
        get_string('b2b_join_loginbtn', 'local_academy'), array('class' => 'btn btn-primary mr-2'));
    // Only offer registration when self-registration is enabled on the site; otherwise signup.php errors.
    if (function_exists('signup_is_enabled') && signup_is_enabled()) {
        $buttons .= html_writer::link(new moodle_url('/login/signup.php'),
            get_string('b2b_join_registerbtn', 'local_academy'), array('class' => 'btn btn-outline-primary'));
    }
    echo html_writer::div($buttons, 'mt-3');
    echo $OUTPUT->footer();
    exit;
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
    $status = $result['status'];
    // When the membership already existed (approved/pending/rejected), prefer an "already …" message
    // so the user is told they are already in / already waiting. Removed users get a fresh request.
    $sm = get_string_manager();
    $statuskey = (!empty($result['existing']) ? 'b2b_join_already_' : 'b2b_join_') . $status;
    if (!$sm->string_exists($statuskey, 'local_academy')) {
        $statuskey = 'b2b_join_' . $status;
    }
    if (!$sm->string_exists($statuskey, 'local_academy')) {
        $statuskey = 'b2b_join_pending';
    }
    $notifytype = ($status === \local_academy\b2b_manager::M_APPROVED) ? 'success'
        : (($status === \local_academy\b2b_manager::M_REJECTED) ? 'warning' : 'info');
    echo $OUTPUT->notification(get_string($statuskey, 'local_academy'), $notifytype);
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/academy/student.php'),
            get_string('b2b_join_goto', 'local_academy'), array('class' => 'btn btn-primary')),
        'mt-3'
    );
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification($e->getMessage(), 'error');
}

echo $OUTPUT->footer();
