<?php
defined('MOODLE_INTERNAL') || die();

function local_payments_extend_navigation(global_navigation $navigation) {
    // Navigation hooks will be added as needed.
}

/**
 * Add "Invoices" and "Payment history" links to the user's preferences (settings) menu, next to
 * the other student entries. Shown only on the user's own preferences page.
 *
 * history.php previously had no menu entry anywhere — reachable only by typing the URL directly,
 * or via the "View Payment History" button shown after a checkout. It is added here next to
 * Invoices since the two pages answer different questions (issued invoices vs. every payment
 * attempt including pending/failed/refunded) and a student should be able to find either.
 */
function local_payments_extend_navigation_user_settings($navigation, $user, $context, $course, $coursecontext) {
    global $USER;
    if (empty($USER->id) || $USER->id != $user->id) {
        return; // only on your own preferences page
    }

    $useraccount = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    $target = $useraccount ?: $navigation;

    $target->add_node(navigation_node::create(
        get_string('myinvoices', 'local_payments'),
        new moodle_url('/local/payments/invoices.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_payments_invoices',
        new pix_icon('i/report', '')
    ));

    $target->add_node(navigation_node::create(
        get_string('paymenthistory', 'local_payments'),
        new moodle_url('/local/payments/history.php'),
        navigation_node::TYPE_SETTING,
        null,
        'local_payments_history',
        new pix_icon('i/log', '')
    ));
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
 * Intercept course view for unenrolled users and redirect to the buy page
 * when the course has active payment pricing.
 */
function local_payments_before_http_headers() {
    global $DB, $USER, $CFG;

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $is_course_view = strpos($script, '/course/view.php') !== false;
    $is_enrol_index = strpos($script, '/enrol/index.php') !== false;
    if (!$is_course_view && !$is_enrol_index) {
        return;
    }

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $courseid = (int) ($_GET['id'] ?? 0);
    if (!$courseid) {
        return;
    }

    $context = context_course::instance($courseid);

    // Let through anyone who can view the course without enrolment (admins, teachers).
    if (has_capability('moodle/course:view', $context)) {
        return;
    }

    // Only an *active* enrolment grants access. An expired one (lapsed
    // subscription/package) must fall through to the buy/enrol gate instead of
    // letting the user hit course/view.php only to be bounced to enrol/index.php.
    if (is_enrolled($context, null, '', true)) {
        return;
    }

    // Only redirect if this course has active payment pricing. A course
    // with no active pricing (removed/diminished) is free — let the user
    // through to Moodle's normal enrolment flow.
    if (!\local_payments\price_resolver::has_pricing($courseid)) {
        return;
    }

    header('Location: ' . $CFG->wwwroot . '/local/payments/buy.php?courseid=' . $courseid);
    exit;
}
