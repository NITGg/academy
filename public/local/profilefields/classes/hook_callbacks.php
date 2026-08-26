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
            if (during_initial_install() || !completion::enabled()) {
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
}
