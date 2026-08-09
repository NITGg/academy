<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_payments.
 *
 * Replaces the legacy local_payments_before_http_headers() callback, which Moodle 4.4+
 * migrated to the \core\hook\output\before_http_headers hook.
 */
class hook_callbacks {

    /**
     * Intercept course view / enrol for unenrolled users and redirect to the buy
     * page when the course has active payment pricing. Runs before HTTP headers.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $CFG;

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

        $context = \context_course::instance($courseid);

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
        // with no active pricing is free — let the user through to Moodle's
        // normal enrolment flow.
        if (!\local_payments\price_resolver::has_pricing($courseid)) {
            return;
        }

        header('Location: ' . $CFG->wwwroot . '/local/payments/buy.php?courseid=' . $courseid);
        exit;
    }
}
