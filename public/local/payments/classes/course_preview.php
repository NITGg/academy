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
 *     for a guest). The lock is core's, not a UI trick. The one exception is an activity a
 *     teacher ticked as a free preview ({@see free_preview}): its view page and its files
 *     preview too, so a shopper can watch a sample lesson (AC-4.9.5);
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

    /** @var int the free-preview activity this request is opening; 0 on a course page. */
    protected static int $cmid = 0;

    /**
     * @var int the activity a URL resolved to, before setup() decided whether to grant. Kept
     *          apart from $cmid so a request that is turned down (hidden course, already
     *          enrolled) does not leave the page believing it is showing a free lesson.
     */
    protected static int $candidatecmid = 0;

    /**
     * The course being previewed on this request, or 0.
     *
     * @return int
     */
    public static function active_courseid(): int {
        return self::$courseid;
    }

    /**
     * The free-preview activity this request is opening, or 0 when it is a course page.
     *
     * @return int
     */
    public static function active_cmid(): int {
        return self::$cmid;
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
     * Is this course locked for whoever is asking — i.e. should its activities show padlocks?
     *
     * True for a visitor previewing the course, and equally for anyone else who got as far as
     * the course page without real access (a course with core "guest access" switched on, an
     * expired subscription). The one question the UI needs answered before it decides whether
     * to draw a link or a lock.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_locked(int $courseid): bool {
        if (!self::is_enabled()) {
            return false;
        }
        if ($courseid <= 0 || $courseid == SITEID) {
            return false;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || is_siteadmin() || is_viewing($context)) {
            return false;
        }

        return !(isloggedin() && !isguestuser() && is_enrolled($context, null, '', true));
    }

    /**
     * Grant read-only access to the requested course page for this request only.
     *
     * Called from the after_config hook, which runs after the session is up (so $USER is
     * known) and before any page script calls require_login().
     *
     * Every request with a session first loses whatever grant the previous request made, then
     * earns its own, or not. What differs between kinds of request is only whether the
     * visitor has MOVED ON, because that is what decides whether the guest login we made for
     * the preview is dropped again:
     *
     *   * a navigation (the browser loading a page) to something that is not a preview means
     *     they left, so the preview is forgotten and the auto-guest dropped;
     *   * a file, an AJAX call or a fetch() fired BY the preview page is not the visitor going
     *     anywhere. Such a request is not granted anything it does not qualify for on its own
     *     (a locked activity's file stays locked), but it must never end the visit either:
     *     the course index loads its state over AJAX right after the page, every open or
     *     collapse of a section in it is another call, and core's ajax.js sends the browser
     *     to the login form the moment one of those finds no login. That is exactly what
     *     happened when the site logo, served by pluginfile.php from the system context, was
     *     mistaken for "the visitor left": a guest who used the sidebar landed on the login
     *     page — but only when the logo was not in the browser cache.
     *
     * @return void
     */
    public static function setup(): void {
        global $CFG, $DB, $USER, $SESSION;

        if (CLI_SCRIPT || WS_SERVER || during_initial_install()) {
            return;
        }
        if (!isset($USER) || !is_object($USER)) {
            // Scripts that run without cookies (theme CSS, images) never have a user.
            return;
        }

        // Always drop a grant left over from an earlier request BEFORE deciding about this
        // one: $USER (and its temp roles) live in the session, so a preview that survived
        // would keep the activities of that course unlocked for the rest of the session.
        // The course it was for stays remembered until a navigation says the visitor moved
        // on, so the page's own AJAX calls can inherit it.
        $remembered = self::revoke();

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $isajax = defined('AJAX_SCRIPT') && AJAX_SCRIPT;
        $isfile = self::script_is($script, '/pluginfile.php');
        $isnavigation = !$isajax && !$isfile && self::is_navigation();

        if (!self::is_enabled()) {
            if ($isnavigation) {
                self::forget();
            }
            return;
        }

        if ($isajax) {
            // Only what the course index needs, and only for the course the last page
            // previewed. Anything else a guest might call gets the same answer core gives a
            // guest with no enrolment.
            $courseid = ($remembered && self::ajax_may_inherit()) ? $remembered : 0;
        } else if ($isfile) {
            $courseid = self::requested_file_courseid($script);
        } else {
            $courseid = self::requested_courseid();
        }

        if (!$courseid) {
            // The visitor has left the preview: if they are only "logged in" because we made
            // them the guest to read a course page, drop them back to anonymous so the rest of
            // the site behaves exactly as it does for any anonymous visitor (login form, not a
            // guest dashboard). A guest who chose to log in as one is left alone.
            if ($isnavigation) {
                self::forget();
                if (!empty($USER->{self::AUTOGUESTKEY}) && isguestuser()) {
                    self::drop_autoguest();
                }
            }
            return;
        }

        if ($isnavigation) {
            // A page that is turned down below (hidden course, already enrolled) must not
            // leave the previous preview's course behind for its AJAX to inherit.
            self::forget();
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
        // Only a navigation starts a session: a file or a call carries the cookie the page
        // already created, and starting a session from an <img> is not worth it.
        if (!isloggedin()) {
            if (!$isnavigation) {
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
        self::$cmid = self::$candidatecmid;
    }

    /**
     * Undo the access a preview grant made on an earlier request in this session gave.
     *
     * The course the grant was for stays remembered on $USER; {@see forget()} clears it.
     *
     * @return int the course the revoked grant was for, or 0 when there was none
     */
    protected static function revoke(): int {
        global $USER;

        if (empty($USER->{self::USERKEY})) {
            return 0;
        }
        $courseid = (int) $USER->{self::USERKEY};
        unset($USER->enrol['tempguest'][$courseid]);

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if ($context) {
            remove_temp_course_roles($context);
        }

        return $courseid;
    }

    /**
     * Stop remembering which course the session last previewed.
     *
     * @return void
     */
    protected static function forget(): void {
        global $USER;

        unset($USER->{self::USERKEY});
    }

    /**
     * Is this request the browser loading a page, as opposed to something a page fetched?
     *
     * Read from the Fetch Metadata header every current browser sends. A request without it
     * (an old browser, curl) is taken for a navigation, which is what every request used to
     * be taken for.
     *
     * @return bool
     */
    protected static function is_navigation(): bool {
        $dest = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
        if ($dest === '') {
            return true;
        }

        return in_array($dest, ['document', 'iframe', 'frame'], true);
    }

    /**
     * Web-service functions an AJAX call may run with the previewed course's grant.
     *
     * The two the course index cannot live without: loading its state after the page, and
     * recording which sections the visitor collapsed (a guest's preferences only live in the
     * session anyway). Anything wider would let a previewer read locked activities through
     * the "get by courses" functions the guest role can otherwise call.
     */
    protected const AJAX_FUNCTIONS = [
        'core_courseformat_get_state',
        'core_courseformat_update_course',
    ];

    /**
     * May this AJAX request inherit the remembered course's grant?
     *
     * lib/ajax/service.php reads the calls it is asked to make from the request body as JSON;
     * so do we, and every call in the batch has to be one of AJAX_FUNCTIONS. The body can be
     * read more than once, so service.php still sees it afterwards.
     *
     * @return bool
     */
    protected static function ajax_may_inherit(): bool {
        $calls = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($calls) || !$calls) {
            return false;
        }
        foreach ($calls as $call) {
            if (!is_array($call) || !in_array($call['methodname'] ?? '', self::AJAX_FUNCTIONS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop the guest login this class made on an earlier request, back to anonymous.
     *
     * Deliberately NOT require_logout(). That ends the session properly - it deletes the
     * session record, issues a new session id and closes the session for the rest of the
     * request - which is right when someone chose to log out, and wrong here, because this
     * runs from after_config on ordinary navigation, every time a previewer moves from a
     * course page to anything else. Two things went wrong because of it:
     *
     *   * the session id changed on every such navigation, so any request still carrying the
     *     previous cookie - a second tab, a prefetched link, a bfcache restore, a subresource
     *     that raced the page - found no session at all. Moodle marks that session
     *     $SESSION->has_timed_out, and the next require_login() sends the visitor to
     *     /login/index.php?loginredirect=1, which greets them with "Your session has timed
     *     out. Please log in again." in the middle of browsing as a guest;
     *   * the session was closed before the page ran, so everything the page then wrote to
     *     $SESSION was dropped on the floor - including $SESSION->wantsurl and the login
     *     form's CSRF token, so a login submitted from that very page failed validation.
     *
     * Dropping the user in the session we already have costs nothing by comparison: the sid
     * stays valid, the session stays open, and there is no session-fixation reason to issue a
     * new id on the way *down* - the guest we are discarding holds no privileges to inherit.
     *
     * $SESSION->lang is carried across because it is the visitor's own choice and survives
     * being logged out of nothing.
     *
     * @return void
     */
    protected static function drop_autoguest(): void {
        global $SESSION;

        $lang = $SESSION->lang ?? null;

        // Resets $USER to the not-logged-in user and $SESSION to a fresh one, keeping this
        // session id and leaving the session open for the page that is about to run.
        \core\session\manager::init_empty_session();

        if ($lang !== null) {
            $SESSION->lang = $lang;
        }

        // Put the session tracking row back to "not logged in" so the online-users list and
        // session cleanup treat it as the anonymous session it now is.
        $record = \core\session\manager::get_session_by_sid(session_id());
        if (!empty($record->id)) {
            $record->userid = 0;
            \core\session\manager::update_session($record);
        }
    }


    /**
     * The course id this request is asking to read, or 0 when the URL is not previewable.
     *
     * Three scripts preview, and only these three: the course page itself, the single-section
     * page it links to when the format shows one section per page (otherwise half the
     * catalogue would preview as a list of section names), and the view page of an activity
     * the teacher published as a free preview (AC-4.9.5). Everything else — every other
     * activity, its files, the reports — is left to core, which is what keeps them locked.
     *
     * course/view.php can also be addressed by ?name= / ?idnumber=, and a handful of old
     * modules accept ?n=<instance> instead of ?id=<cmid>; those fall through to core's normal
     * (login-required) behaviour rather than resolving a lookup this early.
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
        } else if (preg_match('#/mod/[a-z0-9_]+/view\.php$#', $script)) {
            $courseid = self::free_preview_courseid($id);
        } else {
            return 0;
        }

        return ($courseid > 0 && $courseid != SITEID) ? $courseid : 0;
    }

    /**
     * The course of a course module flagged as a free preview, or 0 when it is not one.
     *
     * Records the cmid on the way through, so the rest of the request knows it is looking at
     * a free lesson rather than the course page.
     *
     * @param int $cmid course_modules.id
     * @return int
     */
    protected static function free_preview_courseid(int $cmid): int {
        global $DB;

        if ($cmid <= 0) {
            return 0;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid],
            'id, course, visible, deletioninprogress', IGNORE_MISSING);
        // A hidden activity is not on the course page at all, so it is not on offer either.
        if (!$cm || empty($cm->visible) || !empty($cm->deletioninprogress)) {
            return 0;
        }
        if (!free_preview::is_free((int) $cm->id, (int) $cm->course)) {
            return 0;
        }

        self::$candidatecmid = (int) $cm->id;

        return (int) $cm->course;
    }

    /**
     * The course id of a pluginfile request for a COURSE-level file, or 0.
     *
     * The preview page shows the course picture and the images inside the course and section
     * summaries; those are served by pluginfile.php, which runs its own require_login() and
     * would otherwise bounce a previewer to the login page and leave the page full of broken
     * images. Two kinds of file qualify:
     *
     *   * anything in a COURSE context — the picture and the summaries above;
     *   * the files of an activity flagged as a free preview, because a lesson nobody can
     *     load the video of is not playable (AC-4.9.5).
     *
     * Every other module context is a locked activity's file and stays locked.
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
        if (!$context) {
            return 0;
        }

        if ($context->contextlevel == CONTEXT_MODULE) {
            return self::free_preview_courseid((int) $context->instanceid);
        }

        if ($context->contextlevel != CONTEXT_COURSE) {
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

        $lapsed = 0;

        if (isguestuser() || !isloggedin()) {
            // A guest cannot buy or enrol: the first step is an account.
            $url = new \moodle_url('/login/index.php');
            $label = get_string('preview_login', 'local_payments');
        } else {
            // buy.php is the one gate that already knows every case: paid, free, or covered
            // by the student's subscription.
            $url = new \moodle_url('/local/payments/buy.php', ['courseid' => $courseid]);
            $label = get_string('preview_unlock', 'local_payments');

            // Somebody who is here because their subscription ran out is not a shopper being
            // shown a preview — they are a student locked out of their own work. Say so, and
            // point the button straight at the plan they held rather than at the catalogue.
            $lapsed = self::lapsed_subscription_id($courseid);
            if ($lapsed) {
                $url = new \moodle_url('/local/nit_subscriptions/plan.php', ['id' => $lapsed]);
                $label = get_string('expired_renew', 'local_payments');
            }
        }

        $notice = $lapsed
            ? get_string('expired_notice', 'local_payments')
            : get_string('preview_notice', 'local_payments');

        return \html_writer::div(
            \html_writer::span($notice, 'lp-preview-text')
            . \html_writer::link($url, $label, ['class' => 'btn btn-primary btn-sm lp-preview-cta']),
            'lp-preview-bar' . ($lapsed ? ' lp-preview-expired' : ''),
            ['data-courseid' => $courseid]
        );
    }

    /**
     * The subscription this viewer would renew to get back into this course, or 0.
     *
     * Optional dependency, on purpose: local_payments has to keep working on a site where
     * subscriptions are not installed, exactly as it already does in the other direction
     * (local_nit_subscriptions asks whether price_resolver exists before trusting it).
     *
     * @param int $courseid
     * @return int
     */
    protected static function lapsed_subscription_id(int $courseid): int {
        global $USER;

        if (!class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
            return 0;
        }

        try {
            return (int) \local_nit_subscriptions\subscription_purchase_manager::lapsed_subscription_id(
                $courseid, (int) $USER->id);
        } catch (\Throwable $e) {
            // A banner is not worth a fatal on a course page.
            debugging('local_payments: lapsed-subscription lookup failed: ' . $e->getMessage(), DEBUG_NORMAL);
            return 0;
        }
    }
}
