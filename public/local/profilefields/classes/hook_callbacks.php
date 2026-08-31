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

namespace local_profilefields;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Paths a gated user must still be able to reach.
     *
     * Getting this list wrong locks people out of the site, so it is deliberately
     * generous: the completion page itself (or the redirect loops), every way out
     * (log out, switch role back, the language menu), the login pages themselves,
     * the policy pages, and the whole admin tree - an administrator repairing a
     * misconfiguration must never be the one trapped by it.
     *
     * @var string[] path prefixes, relative to wwwroot
     */
    const ALLOWED = [
        '/local/profilefields/complete.php',
        '/login/',
        '/admin/',
        '/user/policy.php',
        '/user/files.php',
        '/lib/ajax/',
        '/webservice/',
        '/theme/',
        '/help.php',
    ];

    /**
     * Send a user who never finished registering to the page that finishes it.
     *
     * Why on every page and not just after login
     * ------------------------------------------
     * A gate that only fires on the login redirect is not a gate: the user types
     * `/my/` in the address bar and is past it. Core enforces its own two
     * equivalents - the site policy and `user_not_fully_set_up()` - from inside
     * `require_login()`, i.e. on every page. This does the same job one layer out.
     *
     * Why this hook
     * -------------
     * `before_http_headers` is dispatched at the very top of
     * `core_renderer::header()`, BEFORE the page state moves to
     * STATE_PRINTING_HEADER and before a byte reaches the browser, so `redirect()`
     * still sends a clean 302. The later `before_standard_top_of_body` hook is
     * already mid-render - which is why tool_policy only ever *adds HTML* from
     * there - and redirecting from it degrades to a meta refresh painted over a
     * half-drawn page.
     *
     * Fails open. Anything unexpected in here - a half-upgraded install, a field
     * type that throws while reporting whether it is empty - must let the page
     * through, never take the site down.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(
        \core\hook\output\before_http_headers $hook
    ): void {
        global $PAGE, $USER;

        try {
            if (during_initial_install()) {
                return;
            }

            // AC-4.2.8 words the screen shown straight after sign-up, and core's
            // is a plain notice with none of it - no masked address, no countdown
            // on the resend, no way to correct a typo. This is the first moment we
            // can replace it: auth_email::user_signup() has created the account and
            // sent the mail, and is now opening the page to print that notice.
            if (self::redirect_new_signup()) {
                return;
            }

            // WF-5.1: /user/profile.php is core's block-based public profile, and
            // the account screen the wireframes describe is a different thing
            // entirely - editable fields, a password, a way to leave. Rather than
            // rebuild core's page into something it is not, the learner's own
            // profile URL is sent to ours. Everybody else's profile is left alone.
            if (self::redirect_own_profile()) {
                return;
            }

            if (!completion::enabled()) {
                return;
            }
            // Never interrupt a request that cannot show a form: AJAX, web services,
            // CLI, and anything already mid-redirect.
            if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER || NO_MOODLE_COOKIES) {
                return;
            }
            if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
                return;
            }
            // An admin fixing the very setting that traps everyone else must not be
            // trapped by it.
            if (is_siteadmin()) {
                return;
            }
            if (empty($PAGE) || in_array($PAGE->pagelayout, ['embedded', 'maintenance', 'redirect', 'print'], true)) {
                return;
            }
            // The mobile app collects these fields on its own screens, through
            // local_profilefields_get_completion_status - so never redirect inside
            // its WebView. Tested independently of the layout because theme_nit
            // switches app pages to 'embedded' from this same hook, and the order
            // two callbacks run in is not guaranteed.
            if (self::is_app_request()) {
                return;
            }
            if (self::is_allowed_path()) {
                return;
            }
            if (completion::is_complete($USER)) {
                return;
            }
        } catch (\Throwable $e) {
            debugging('local_profilefields completion gate skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }

        // Come back to where they were heading once they are done.
        $returnurl = '';
        try {
            $here = $PAGE->url->out_as_local_url(false);
            $returnurl = ($here === '' || strpos($here, '/local/profilefields/complete.php') === 0) ? '' : $here;
        } catch (\Throwable $e) {
            $returnurl = '';
        }

        redirect(completion::url($returnurl));
    }

    /**
     * Is this page being viewed inside the mobile app's WebView?
     *
     * The same three signals theme_nit uses to strip the page chrome: the app's
     * User-Agent, the `nitembed` flag it can append to a URL, and the session flag
     * set on the first match so in-activity links stay recognised.
     *
     * @return bool
     */
    protected static function is_app_request(): bool {
        global $SESSION;

        return !empty($SESSION->theme_nit_appembed)
            || \core_useragent::is_moodle_app()
            || (bool) optional_param('nitembed', 0, PARAM_BOOL);
    }

    /**
     * Is the request already on a page a gated user is allowed to see?
     *
     * @return bool
     */
    protected static function is_allowed_path(): bool {
        global $CFG;

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        // wwwroot may live in a subdirectory (…/moodle-new), which is part of
        // REQUEST_URI but not of the paths above.
        $root = (string) parse_url($CFG->wwwroot, PHP_URL_PATH);
        if ($root !== '' && strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }

        foreach (self::ALLOWED as $allowed) {
            if (strpos($path, $allowed) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send a learner who has just signed up to our own confirmation notice.
     *
     * The id is left in the session by {@see observer::user_created()} and is
     * consumed here whatever the outcome, so a stale value cannot survive to
     * hijack a later page load.
     *
     * @return bool true when the request has been redirected away
     */
    protected static function redirect_new_signup(): bool {
        global $SESSION;

        $id = (int) ($SESSION->local_profilefields_justsignedup ?? 0);
        if (!$id) {
            return false;
        }

        unset($SESSION->local_profilefields_justsignedup);

        // Only on the page that would otherwise print core's notice. Anything
        // else - an account created by an administrator, by the app's web
        // service, by a CLI upload - keeps its own flow.
        if (self::script_path() !== '/login/signup.php') {
            return false;
        }

        redirect(new \moodle_url('/local/profilefields/verify.php', ['id' => $id]));

        return true;
    }

    /**
     * Send a learner looking at their own profile to the account screen.
     *
     * Narrow on purpose. Only the learner's *own* profile moves: `?id=` naming
     * somebody else is core's public profile page and stays that way, which is
     * also what AC-4.5.11 needs, since the public instructor profile is a
     * different screen with a different set of fields.
     *
     * Four other ways out, all of them cases where core's page is the right
     * answer and a redirect would be taking something away:
     *
     * - `?course=` - the profile as seen from inside a course carries that
     *   course's roles, groups and activity reports, none of which the account
     *   screen has;
     * - `?edit=` / `?reset=` - somebody is arranging the blocks on the page;
     * - `?core=1` - a deliberate escape hatch, so an administrator can still
     *   reach the stock page without turning the redirect off site-wide;
     * - the mobile app's WebView, which has its own account screens.
     *
     * @return bool true when the request has been redirected away
     */
    protected static function redirect_own_profile(): bool {
        global $USER, $PAGE;

        if (self::script_path() !== '/user/profile.php') {
            return false;
        }

        if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER || NO_MOODLE_COOKIES) {
            return false;
        }

        // Not while logged in as somebody else: the point of that session is to
        // see what they see, and their account screen is not ours to open.
        if (!isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return false;
        }

        if (self::is_app_request()) {
            return false;
        }

        if (!empty($PAGE) && in_array($PAGE->pagelayout,
                ['embedded', 'maintenance', 'redirect', 'print'], true)) {
            return false;
        }

        if (optional_param('core', 0, PARAM_BOOL)) {
            return false;
        }

        $courseid = optional_param('course', SITEID, PARAM_INT);
        if ($courseid != SITEID) {
            return false;
        }

        if (optional_param('edit', null, PARAM_BOOL) !== null
                || optional_param('reset', null, PARAM_BOOL) !== null) {
            return false;
        }

        // No id at all means "mine", which is how the wireframe's link is written.
        $id = optional_param('id', 0, PARAM_INT);
        if ($id !== 0 && $id !== (int) $USER->id) {
            return false;
        }

        redirect(account::url());

        return true;
    }

    /**
     * The request path, with any wwwroot subdirectory stripped off.
     *
     * @return string e.g. "/login/confirm.php"
     */
    protected static function script_path(): string {
        global $CFG;

        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $root = (string) parse_url($CFG->wwwroot, PHP_URL_PATH);

        if ($root !== '' && strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }

        return $path;
    }

    /**
     * Apply AC-4.2's rules to core's confirmation flow, before core runs.
     *
     * `after_config` is dispatched at the end of setup.php - the session is up,
     * `$DB` is live, and not a line of the page has run yet. That is the only
     * moment at which we can still decide that `/login/confirm.php` should not
     * confirm and that `/login/index.php` should not send: both make their move
     * in page code, and a later hook would be arguing with a decision already
     * taken.
     *
     * Two interceptions, and nothing else on any other URL:
     *
     * - **an expired link** (AC-4.2.10, AC-4.2.11). Core has no expiry at all; it
     *   compares the secret and confirms. Caught here and turned into the
     *   specification's message plus a way to ask for a new one.
     *
     * - **a resend** (AC-4.2.2, AC-4.2.3, AC-4.2.4). Refused when the account is
     *   inside its cooldown or over its hourly ceiling; otherwise the secret is
     *   rotated first, so the mail core is about to send carries a new link and
     *   every earlier one stops working.
     *
     * Fails open, like the sibling callback above: an exception here must not be
     * able to make the login page unreachable.
     *
     * @param \core\hook\after_config $hook
     * @return void
     */
    public static function after_config(\core\hook\after_config $hook): void {
        try {
            if (during_initial_install() || CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
                return;
            }

            $path = self::script_path();

            if ($path === '/login/confirm.php') {
                self::guard_confirm_link();
                return;
            }

            if ($path === '/login/index.php' && optional_param('resendconfirmemail', false, PARAM_BOOL)) {
                self::guard_resend();
                return;
            }

            self::resume_remembered_session();
        } catch (\Throwable $e) {
            debugging('local_profilefields verification guard: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Rebuild a session from a "Remember me" cookie, when there is one to use.
     *
     * This is the half of AC-4.3.5 that makes the 30 days visible. The session
     * itself still expires after the configured 24 hours - the specification asks
     * for that, and it is a site-wide setting we do not want to move - so what the
     * learner experiences as "still signed in" is this: the session is gone, the
     * cookie is not, and a new session is built from it before the page notices.
     *
     * Why here. `after_config` runs at the end of setup.php, with the session
     * started and $DB live, and before any page code has decided whether the
     * visitor is logged in. `require_login()` runs later and would already have
     * redirected to the login screen.
     *
     * The cost on a normal request is one array lookup: no cookie, no work. Only a
     * visitor who is signed out *and* holding a token reaches the database.
     *
     * @return void
     */
    protected static function resume_remembered_session(): void {
        global $DB;

        if (isloggedin() || !rememberme::enabled()) {
            return;
        }

        // Pages that must not acquire a session as a side effect: the login screen
        // is mid-authentication, and logout has just deliberately ended one - being
        // silently signed back in there would look like the site refusing to let go.
        $path = self::script_path();
        if (in_array($path, ['/login/logout.php', '/login/confirm.php'], true)) {
            return;
        }

        $user = rememberme::resume();
        if (!$user) {
            return;
        }

        // complete_user_login() expects the record get_complete_user_data() builds
        // - custom fields, preferences, the lot - not the bare row the token check
        // needed.
        $full = get_complete_user_data('id', $user->id);
        if (!$full) {
            return;
        }

        complete_user_login($full);
    }

    /**
     * Answer a confirmation link in AC-4.2's words rather than core's.
     *
     * Three of the four outcomes are settled here; the fourth - a link that is
     * current, unexpired and genuine - is passed straight through, because
     * confirming an account is core's job and reimplementing it would mean two
     * answers to "is this link genuine?" that could disagree.
     *
     * - **Already confirmed** (AC-4.2.13). Core prints "Registration has already
     *   been confirmed" above a button to the course list, which is a dead end for
     *   somebody who is not logged in. The specification's sentence sends them to
     *   the login page, which is where they were trying to get.
     *
     * - **Superseded** (AC-4.2.4, AC-4.2.11). A link built on a secret that a
     *   later resend has replaced. Core calls this `invalidconfirmdata` - an
     *   exception page reading "Invalid confirmation data" - which reads as a
     *   broken site rather than as "you have a newer email, open that one".
     *
     * - **Expired** (AC-4.2.10, AC-4.2.11). Core has no expiry at all.
     *
     * The last two are the same event as far as the learner is concerned - the
     * link in their hand does not work and they need another - so they get the
     * same screen, which is the one with the Resend button on it.
     *
     * @return void
     */
    protected static function guard_confirm_link(): void {
        $data = optional_param('data', '', PARAM_RAW);
        $user = verification::user_from_link($data, optional_param('s', '', PARAM_RAW));

        // A link naming no account at all stays core's to refuse: there is nothing
        // here we could say about it that would not amount to confirming which
        // usernames exist.
        if (!$user) {
            return;
        }

        if (!empty($user->confirmed)) {
            redirect(
                new \moodle_url('/login/index.php'),
                get_string('verifyalreadydone', 'local_profilefields'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }

        $secret = verification::secret_from_link($data, optional_param('p', '', PARAM_RAW));

        if (verification::secret_is_current($user, $secret) && !verification::link_expired($user)) {
            return;
        }

        redirect(
            new \moodle_url('/local/profilefields/verify.php', ['id' => $user->id, 'expired' => 1])
        );
    }

    /**
     * Let a resend through, hold it back, or freshen the secret before it goes.
     *
     * @return void
     */
    protected static function guard_resend(): void {
        global $DB, $CFG;

        $username = trim(optional_param('username', '', PARAM_RAW));
        if ($username === '') {
            return;
        }

        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);

        // An address that is already confirmed has nothing to resend, and one we
        // do not recognise must not learn that it is unrecognised from how fast
        // this page answers.
        if (!$user || !empty($user->confirmed)) {
            return;
        }

        $refusal = verification::refuse_resend($user);
        if ($refusal !== null) {
            redirect(
                new \moodle_url('/local/profilefields/verify.php', ['id' => $user->id]),
                $refusal,
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        // AC-4.2.4: the mail core is about to send must invalidate its predecessors.
        verification::rotate_secret($user);
    }
}
