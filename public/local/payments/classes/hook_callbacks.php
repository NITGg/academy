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
     * Open the course page as a locked preview for visitors who have not bought it yet.
     *
     * Runs as early as a plugin can (straight after setup.php), because the decision has to
     * be made before /course/view.php calls require_login(). See \local_payments\course_preview.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        course_preview::setup();
    }

    /**
     * Send a student who is trying to ENROL in a course they have not paid for to the buy
     * page. Runs before HTTP headers.
     *
     * Note what is deliberately NOT intercepted any more: /course/view.php. The course page
     * is the product page — course_preview lets anyone read it with every activity locked,
     * so redirecting it to the checkout would hide the very thing being sold. Only the
     * enrolment attempt itself is routed to payment, which is also where core sends anyone
     * who tries to open a locked activity.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE, $SESSION;

        // On a course page the viewer has no real access to, tell the theme to padlock the
        // activity links. Asked of the PAGE rather than of course_preview, so the class is
        // also there when the visitor got in some other way — e.g. a course with core
        // "guest access" switched on, where the activities are locked by
        // local_payments_after_require_login() and must look locked too.
        if (self::is_locked_course_view($PAGE)) {
            $PAGE->add_body_class('local-payments-preview');
        }

        // Never redirect a preview page — this IS the page the visitor asked for.
        if (course_preview::active_courseid()) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script, '/enrol/index.php') === false) {
            return;
        }

        $courseid = (int) ($_GET['id'] ?? 0);
        if (!$courseid) {
            return;
        }

        // A guest (including the auto-guest of a preview) cannot buy or enrol anything:
        // the first step is an account, so send them to log in and come back.
        if (!isloggedin() || isguestuser()) {
            $SESSION->wantsurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            header('Location: ' . $CFG->wwwroot . '/login/index.php');
            exit;
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

        // Route unenrolled students to our buy/register page for BOTH paid and
        // free courses: paid shows the checkout, free shows a one-click "Register
        // for free" button (core self-enrolment may be off, which is what caused
        // "You cannot enrol yourself in this course"). buy.php is not intercepted
        // here, so there is no redirect loop.
        header('Location: ' . $CFG->wwwroot . '/local/payments/buy.php?courseid=' . $courseid);
        exit;
    }

    /**
     * Is this a course page being read by somebody who has no real access to the course?
     *
     * @param \moodle_page $page
     * @return bool
     */
    protected static function is_locked_course_view(\moodle_page $page): bool {
        if (strpos((string) $page->pagetype, 'course-view') !== 0) {
            return false;
        }

        return course_preview::is_locked((int) ($page->course->id ?? 0));
    }
}
