<?php
defined('MOODLE_INTERNAL') || die();

function local_payments_extend_navigation(global_navigation $navigation) {
    // Navigation hooks will be added as needed.
}

function local_payments_extend_navigation_course(\navigation_node $navigation, \stdClass $course, \context_course $context) {
    if (has_capability('local/payments:managecoursepricing', $context)) {
        $url = new \moodle_url('/local/payments/course_pricing.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursepricing', 'local_payments'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'local_payments_pricing',
            new \pix_icon('i/payment', '')
        );
    }
}

/**
 * Add a "Payment history" link to the user's own profile page.
 *
 * This is the student-facing entry point to /local/payments/history.php —
 * it appears under the "Miscellaneous" category on the profile page for the
 * logged-in user (and to admins viewing other users).
 */
function local_payments_myprofile_navigation(\core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    global $USER;

    if (!$iscurrentuser && !is_siteadmin()) {
        return;
    }

    $url = new \moodle_url('/local/payments/history.php');
    $node = new \core_user\output\myprofile\node(
        'miscellaneous',
        'local_payments_history',
        get_string('paymenthistory', 'local_payments'),
        null,
        $url
    );
    $tree->add_node($node);
}

// The course-view payment gate previously implemented here as
// local_payments_before_http_headers() is now a hook callback
// (\local_payments\hook_callbacks::before_http_headers) registered in db/hooks.php,
// as required by the Moodle 4.4+ Hooks API.

/**
 * Lock every activity for anyone who is not actually enrolled.
 *
 * Core calls this at the very end of require_login(), with $cm set whenever the request is
 * for an activity (a module page, and any file that module serves through pluginfile.php).
 * That is the one place that sees ALL of them, whichever way access was granted — which
 * matters here, because a course with core "guest access" turned on hands a guest the run
 * of the place, and a locked preview that leaves the activities open is not a lock.
 *
 * The course page itself is deliberately NOT locked: it is the product page, and
 * {@see \local_payments\course_preview} exists to open it. Only activities close.
 *
 * @param mixed $courseorid course object or id passed to require_login()
 * @param bool|null $autologinguest
 * @param cm_info|null $cm the activity being opened, null for course-level pages
 * @param bool $setwantsurltome
 * @param bool $preventredirect true when the caller cannot be redirected (AJAX, web services)
 * @return void
 */
function local_payments_after_require_login($courseorid = null, $autologinguest = null, $cm = null,
        $setwantsurltome = true, $preventredirect = false) {
    global $CFG, $SESSION;

    if (empty($cm) || CLI_SCRIPT || WS_SERVER || during_initial_install()) {
        return;
    }
    if (!\local_payments\course_preview::is_enabled()) {
        // Feature off: leave Moodle's own access rules alone.
        return;
    }

    $courseid = (int) $cm->course;
    if (!$courseid || $courseid == SITEID) {
        return;
    }

    $context = context_course::instance($courseid, IGNORE_MISSING);
    if (!$context) {
        return;
    }

    // Real access — enrolled student, teacher, manager, admin — passes straight through.
    if (is_siteadmin() || is_viewing($context)) {
        return;
    }
    if (isloggedin() && !isguestuser() && is_enrolled($context, null, '', true)) {
        return;
    }

    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

    if ($preventredirect) {
        // AJAX / anything that cannot follow a redirect: refuse, do not silently allow.
        throw new require_login_exception('Activity locked until enrolled');
    }

    if (!isloggedin() || isguestuser()) {
        // No account yet: the first step is logging in, then buy/enrol.
        $SESSION->wantsurl = $courseurl->out(false);
        redirect(new moodle_url('/login/index.php'), get_string('preview_locked', 'local_payments'),
            null, \core\output\notification::NOTIFY_INFO);
    }

    // Logged in but without access: buy.php knows every case (paid, free, subscription).
    redirect(new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]),
        get_string('preview_locked', 'local_payments'), null, \core\output\notification::NOTIFY_INFO);
}
