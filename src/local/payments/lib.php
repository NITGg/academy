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

    if (is_enrolled($context)) {
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
