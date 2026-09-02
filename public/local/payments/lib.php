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

    if (has_capability('local/payments:viewcoursepayments', $context)) {
        $url = new \moodle_url('/local/payments/transactions.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursepayments', 'local_payments'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'local_payments_coursepayments',
            new \pix_icon('i/report', '')
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

    // A lesson the teacher published as a free preview stays open: that is the whole point
    // of the flag, and it is the only door this function leaves unlocked (AC-4.9.5).
    if (\local_payments\free_preview::is_free((int) $cm->id, $courseid)) {
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

/**
 * Add the "Free preview" tick to every activity settings form (AC-4.9.5).
 *
 * Core calls this for every plugin at the end of moodleform_mod::standard_coursemodule_elements(),
 * which is the supported way to put a setting of our own on /course/modedit.php — no core file
 * is touched. The tick is inserted right after the "Availability" control inside *Common module
 * settings*, because that is where a teacher already goes to decide who can see this activity;
 * when core moves that element the tick simply lands at the end of the form instead.
 *
 * The value is written back by local_payments_coursemodule_edit_post_actions().
 *
 * @param moodleform_mod $formwrapper the activity settings form
 * @param MoodleQuickForm $mform the form itself
 * @return void
 */
function local_payments_coursemodule_standard_elements($formwrapper, $mform) {
    if (!\local_payments\course_preview::is_enabled()) {
        // Preview turned off site-wide: nothing to exempt from, so do not offer the choice.
        return;
    }

    $field = \local_payments\free_preview::FORMFIELD;

    $element = $mform->createElement('advcheckbox', $field, get_string('freepreview', 'local_payments'),
        get_string('freepreview_label', 'local_payments'), ['group' => null], [0, 1]);

    // Sit with the other "who can see this" settings rather than at the foot of the form.
    // The first anchor that exists wins; a module form that has none of them (some have no
    // groups and no availability section) just gets the tick appended.
    $placed = false;
    foreach (['availabilityconditionsjson', 'groupmode', 'visible'] as $anchor) {
        if ($mform->elementExists($anchor)) {
            $mform->insertElementBefore($element, $anchor);
            $placed = true;
            break;
        }
    }
    if (!$placed) {
        $mform->addElement($element);
    }

    $mform->setType($field, PARAM_BOOL);
    $mform->addHelpButton($field, 'freepreview', 'local_payments');

    // Existing activity: show the flag it already carries.
    $cm = $formwrapper->get_coursemodule();
    $default = (!empty($cm->id) && \local_payments\free_preview::is_free((int) $cm->id, (int) $cm->course)) ? 1 : 0;
    $mform->setDefault($field, $default);
}

/**
 * Save the "Free preview" tick after an activity is created or updated.
 *
 * Core calls this from add_moduleinfo()/update_moduleinfo() once the course module exists,
 * so $moduleinfo->coursemodule is the cmid the flag belongs to.
 *
 * @param stdClass $moduleinfo the submitted module data, with coursemodule set
 * @param stdClass $course
 * @return stdClass $moduleinfo, unchanged
 */
function local_payments_coursemodule_edit_post_actions($moduleinfo, $course) {
    $field = \local_payments\free_preview::FORMFIELD;

    // The property is absent when the form did not offer the tick (preview switched off) or
    // when the module was created by something other than the form (restore, web service).
    // Leaving the flag alone is the right answer in both cases.
    if (!property_exists($moduleinfo, $field)) {
        return $moduleinfo;
    }

    \local_payments\free_preview::set(
        (int) $moduleinfo->coursemodule,
        (int) $course->id,
        !empty($moduleinfo->$field)
    );

    return $moduleinfo;
}
