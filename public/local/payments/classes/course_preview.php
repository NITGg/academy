<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Locked course preview: let ANY visitor read /course/view.php, with the activities locked.
 *
 * The shop needs a product page, and the course page is it. Out of the box nobody who has
 * not paid can reach it: core's require_login() sends a visitor who is not logged in to the
 * login form, and a logged-in student who is not enrolled to /enrol/index.php (which this
 * plugin then turns into the buy page). So the course a student is being asked to buy is
 * the one page they cannot look at.
 *
 * This class grants read access for the length of ONE request, and only on
 * /course/view.php, using the same mechanism core's own guest access uses
 * (load_temp_course_role() + $USER->enrol['tempguest']) — see enrol_guest::try_guestaccess().
 * It runs from the after_config hook, i.e. before the course page calls require_login().
 *
 * Because the grant covers that one URL only:
 *
 *   * every activity stays locked — /mod/xxx/view.php, and any file served through
 *     pluginfile.php, still run require_login() with no grant, so core bounces the visitor
 *     to /enrol/index.php, which hook_callbacks turns into the buy page (or the login page
 *     for a guest). The lock is core's, not a UI trick;
 *   * nothing leaks into the next request: any grant made earlier in the session is revoked
 *     at the start of every request before a new one is considered.
 *
 * A not-logged-in visitor is logged in as the site guest first, exactly as core does when
 * $CFG->autologinguests is on — but only for this one page, so every other part of the site
 * keeps sending anonymous users to the login form as before.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_preview {

    /** @var string admin setting that turns the preview on/off. */
    public const SETTING = 'course_preview';

    /** @var string key under which a granted preview is remembered on $USER (to revoke it later). */
    protected const USERKEY = 'local_payments_preview';

    /** @var string marks a guest session this class created, so it can be dropped again. */
    protected const AUTOGUESTKEY = 'local_payments_autoguest';

    /** @var int the course this request is previewing; 0 when the request is not a preview. */
    protected static int $courseid = 0;

    /**
     * The course being previewed on this request, or 0.
     *
     * @return int
     */
    public static function active_courseid(): int {
        return self::$courseid;
    }

    /**
     * Is the locked preview turned on for this site? Defaults to on when never configured.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $value = get_config('local_payments', self::SETTING);

        return $value === false ? true : (bool) $value;
    }

    /**
     * Grant read-only access to the requested course page for this request only.
     *
     * Called from the after_config hook, which runs after the session is up (so $USER is
     * known) and before any page script calls require_login().
     *
     * @return void
     */
    public static function setup(): void {
        global $CFG, $DB, $USER, $SESSION;

        if (CLI_SCRIPT || WS_SERVER || during_initial_install()) {
            return;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            // Requests fired BY a preview page (user preferences, toasts…). They must not be
            // treated as "the visitor left the preview", or the cleanup below would end the
            // session mid-visit.
            return;
        }
        if (!isset($USER) || !is_object($USER)) {
            // Scripts that run without cookies (theme CSS, images) never have a user.
            return;
        }

        // Always drop a grant left over from an earlier request BEFORE deciding about this
        // one: $USER (and its temp roles) live in the session, so a preview that survived
        // would keep the activities of that course unlocked for the rest of the session.
        self::revoke();

        if (!self::is_enabled()) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $courseid = self::requested_courseid();
        $isfile = false;
        if (!$courseid) {
            $courseid = self::requested_file_courseid($script);
            $isfile = ($courseid > 0);
        }
        if (!$courseid) {
            // The visitor has left the preview: if they are only "logged in" because we made
            // them the guest to read a course page, end that session so the rest of the site
            // behaves exactly as it does for any anonymous visitor (login form, not a guest
            // dashboard). A guest who chose to log in as one is left alone.
            if (!empty($USER->{self::AUTOGUESTKEY}) && isguestuser()) {
                require_logout();
            }
            return;
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, visible, category', IGNORE_MISSING);
        if (!$course) {
            return;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        // Anyone who can already get in — enrolled student, teacher, manager, admin — must be
        // left completely alone: they get the real course, not a preview of it.
        if (isloggedin() && !isguestuser()) {
            if (is_siteadmin() || is_viewing($context) || is_enrolled($context, null, '', true)) {
                return;
            }
        }

        // A hidden course, or one in a category this visitor may not browse, stays hidden:
        // the preview shows what the catalogue already shows, nothing more.
        if (!\core_course_category::can_view_course_info($course)) {
            return;
        }

        // Not logged in at all: become the site guest, the same way core does when
        // $CFG->autologinguests is on (see require_login() in lib/moodlelib.php). Scoped to
        // this one page, so the rest of the site still asks anonymous users to log in.
        // Never for a file request: those carry the session cookie the page already
        // created, and starting a session from an <img> is not worth it.
        if (!isloggedin()) {
            if ($isfile) {
                return;
            }
            if (!empty($CFG->forcelogin) || empty($CFG->siteguest)) {
                return;
            }
            if (!$guest = get_complete_user_data('id', $CFG->siteguest)) {
                return;
            }
            $lang = $SESSION->lang ?? $CFG->lang;
            complete_user_login($guest);
            $USER->autologinguest = true;
            $USER->{self::AUTOGUESTKEY} = true;
            $SESSION->lang = $lang;
        }

        if (empty($CFG->guestroleid)) {
            // No guest role configured — there is no read-only role to lend. Leave core alone.
            return;
        }

        // The grant itself: the guest role in this course for capability checks, plus the
        // "temporary guest" marker require_login() looks for before it asks the enrol plugins.
        load_temp_course_role($context, $CFG->guestroleid);
        $USER->enrol['tempguest'][$courseid] = ENROL_MAX_TIMESTAMP;
        $USER->{self::USERKEY} = $courseid;

        self::$courseid = $courseid;
    }

    /**
     * Undo a preview grant made on an earlier request in this session.
     *
     * @return void
     */
    protected static function revoke(): void {
        global $USER;

        if (empty($USER->{self::USERKEY})) {
            return;
        }
        $courseid = (int) $USER->{self::USERKEY};
        unset($USER->{self::USERKEY});
        unset($USER->enrol['tempguest'][$courseid]);

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            remove_temp_course_roles($context);
        }
    }

    /**
     * The course id this request is asking to read, or 0 when the URL is not a course page.
     *
     * Two scripts preview, and only these two: the course page itself, and the single-section
     * page it links to when the format shows one section per page (otherwise half the
     * catalogue would preview as a list of section names). Everything else — activities,
     * files, reports — is left to core, which is what keeps them locked.
     *
     * course/view.php can also be addressed by ?name= / ?idnumber=; those fall through to
     * core's normal (login-required) behaviour rather than resolving a lookup this early.
     *
     * @return int
     */
    protected static function requested_courseid(): int {
        global $DB;

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            return 0;
        }

        if (self::script_is($script, '/course/view.php')) {
            $courseid = $id;
        } else if (self::script_is($script, '/course/section.php')) {
            $courseid = (int) $DB->get_field('course_sections', 'course', ['id' => $id], IGNORE_MISSING);
        } else {
            return 0;
        }

        return ($courseid > 0 && $courseid != SITEID) ? $courseid : 0;
    }

    /**
     * The course id of a pluginfile request for a COURSE-level file, or 0.
     *
     * The preview page shows the course picture and the images inside the course and section
     * summaries; those are served by pluginfile.php, which runs its own require_login() and
     * would otherwise bounce a previewer to the login page and leave the page full of broken
     * images. Only files that live in a course context qualify — anything in a module context
     * is an activity's file and stays locked.
     *
     * @param string $script value of $_SERVER['SCRIPT_NAME']
     * @return int
     */
    protected static function requested_file_courseid(string $script): int {
        if (!self::script_is($script, '/pluginfile.php')) {
            return 0;
        }

        // With slasharguments on the path is in PATH_INFO, otherwise in ?file=.
        $path = (string) ($_SERVER['PATH_INFO'] ?? ($_GET['file'] ?? ''));
        if (!preg_match('#^/?(\d+)/#', $path, $matches)) {
            return 0;
        }

        $context = \context::instance_by_id((int) $matches[1], IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_COURSE) {
            return 0;
        }

        return ($context->instanceid != SITEID) ? (int) $context->instanceid : 0;
    }

    /**
     * Does the running script have this path? Compared on the tail so a Moodle installed in
     * a subdirectory ("/moodle/course/view.php") matches too.
     *
     * @param string $script value of $_SERVER['SCRIPT_NAME']
     * @param string $path e.g. '/course/view.php'
     * @return bool
     */
    protected static function script_is(string $script, string $path): bool {
        return substr_compare($script, $path, -strlen($path)) === 0;
    }

    /**
     * The "everything here is locked" bar shown at the top of a previewed course.
     *
     * @param int $courseid
     * @return string HTML
     */
    public static function banner_html(int $courseid): string {
        global $CFG;

        if (isguestuser() || !isloggedin()) {
            // A guest cannot buy or enrol: the first step is an account.
            $url = new \moodle_url('/login/index.php');
            $label = get_string('preview_login', 'local_payments');
        } else {
            // buy.php is the one gate that already knows every case: paid, free, or covered
            // by the student's subscription.
            $url = new \moodle_url('/local/payments/buy.php', ['courseid' => $courseid]);
            $label = get_string('preview_unlock', 'local_payments');
        }

        return \html_writer::div(
            \html_writer::span(get_string('preview_notice', 'local_payments'), 'lp-preview-text')
            . \html_writer::link($url, $label, ['class' => 'btn btn-primary btn-sm lp-preview-cta']),
            'lp-preview-bar',
            ['data-courseid' => $courseid]
        );
    }
}
