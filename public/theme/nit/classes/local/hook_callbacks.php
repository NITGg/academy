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

namespace theme_nit\local;

/**
 * Hook callbacks for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Two things a visitor's account links promise and core does not keep:
     * "Log in" brings you back to the page you were on, and so does "Log out".
     *
     * after_config, because it runs before the login and logout scripts
     * themselves and before any redirect either may issue (an alternate login
     * URL, for one) - the one moment guaranteed to precede everything core does
     * with the session. Cheap on every other request: the script name is
     * checked first.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SCRIPT;

        switch ($SCRIPT ?? '') {
            case '/login/index.php':
                self::remember_login_return();
                break;
            case '/login/logout.php':
                self::logout_to_current_page();
                break;
        }
    }

    /**
     * Honour the `nitreturn=` the navbar's "Log in" link carries: the page the
     * visitor was on, which is where they should land once signed in.
     *
     * The login page decides where to send a signed-in user from
     * $SESSION->wantsurl, and reads the HTTP referer into it only when it is
     * empty - a guess, and one that loses to whatever a "log in first" door
     * (a locked course, enrol/index.php) left there earlier in the session. The
     * navbar link therefore names the page outright (core_renderer::
     * navbar_login_url), and this writes it into wantsurl ahead of the stale
     * value. From there core does the rest: login/index.php redirects to it
     * after a password or an OAuth2 login, signup.php keeps it, auth_email
     * carries it across the confirmation email, confirm.php restores it.
     *
     * The value is a PARAM_LOCALURL - a page on this site, or nothing - so the
     * link cannot be made to send a visitor elsewhere after they log in.
     *
     * @return void
     */
    protected static function remember_login_return(): void {
        global $SESSION;

        // Somebody already signed in has no Log in link to have clicked;
        // whatever they are doing on the login page is not this.
        if (isloggedin() && !isguestuser()) {
            return;
        }

        $return = optional_param('nitreturn', '', PARAM_LOCALURL);
        if ($return === '') {
            return;
        }

        $SESSION->wantsurl = (new \moodle_url($return))->out(false);
    }

    /**
     * Areas a visitor is never sent back to after logging out.
     *
     * Path prefixes under wwwroot. Each exists only for a signed-in account -
     * the dashboard, the profile, administration, messaging, grades, an
     * activity page, our account and management screens - so landing there
     * signed out would only bounce the visitor onto the login form, which
     * looks like the site refusing to let them go. Any path with a `manage`
     * segment counts too, whichever plugin it belongs to. The home page stands
     * in instead, as core does.
     */
    const LOGOUT_NO_RETURN_PATHS = [
        '/admin/', '/my/', '/user/', '/login/', '/message/', '/calendar/', '/grade/',
        '/report/', '/mod/', '/badges/', '/notes/', '/blog/', '/cohort/', '/group/',
        '/backup/', '/enrol/', '/question/', '/course/edit', '/course/modedit',
        '/course/loginas', '/course/user.php',
        '/local/profilefields/account.php', '/local/profilefields/complete.php',
        '/local/profilefields/verify.php', '/theme/nit/gallery.php',
    ];

    /**
     * Sign the user out and send them back to the page they logged out from.
     *
     * Core's logout.php ends every session on the site home, and offers no way
     * to say otherwise: the only thing that can change its destination is an
     * auth plugin's logoutpage_hook(). The Log out link is core's as well -
     * built in user_get_user_navigation_info() *after* the extend_user_menu
     * hook has run, so no plugin can decorate it - which leaves the request's
     * own evidence of where the click came from: the HTTP referer, a page on
     * this site or nothing (PARAM_LOCALURL). That is the same source core's
     * login page reads its return page from, and a link click on the site
     * always sends it.
     *
     * Exactly what logout.php does is repeated here, in its order - the auth
     * plugins' logoutpage_hook() (each may redirect elsewhere by changing
     * $redirect, and that still wins), then require_logout(), then the
     * redirect - so an auth plugin that takes part in logout, single-logout
     * included, sees no difference. Everything else is left to core: no
     * session to end, a missing or stale sesskey (core prints its confirmation
     * page), `loginpage=1` (core sends the visitor to the login form), or a
     * page there is no point returning to ({@see self::LOGOUT_NO_RETURN_PATHS}).
     *
     * @return void
     */
    protected static function logout_to_current_page(): void {
        global $redirect;

        if (!isloggedin() || optional_param('loginpage', 0, PARAM_BOOL)) {
            return;
        }
        // The same check as logout.php, deliberately not required_param: a
        // missing key means core's "are you sure?" page, not an error.
        if (!confirm_sesskey(optional_param('sesskey', '__notpresent__', PARAM_RAW))) {
            return;
        }

        $return = self::logout_return_url();
        if ($return === null) {
            return;
        }

        $redirect = $return;
        foreach (get_enabled_auth_plugins() as $authname) {
            get_auth_plugin($authname)->logoutpage_hook();
        }

        require_logout();

        redirect($redirect);
    }

    /**
     * The page the current user should come back to after logging out, as a
     * full URL - or null when there is none worth returning to.
     *
     * @return string|null
     */
    protected static function logout_return_url(): ?string {
        $referer = get_local_referer(false);
        if ($referer === '') {
            return null;
        }

        try {
            $local = (new \moodle_url($referer))->out_as_local_url(false);
        } catch (\Throwable $e) {
            return null;
        }

        $path = (string) parse_url($local, PHP_URL_PATH);
        if ($local === '' || $path === '' || $path === '/' || $path === '/index.php') {
            return null;
        }
        foreach (self::LOGOUT_NO_RETURN_PATHS as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return null;
            }
        }
        if (preg_match('~/manage~', $path)) {
            return null;
        }

        return (new \moodle_url($local))->out(false);
    }

    /**
     * Force a chrome-free ("embedded") page layout when a page is being viewed
     * inside the mobile app's in-app WebView.
     *
     * Why this exists
     * ---------------
     * The Flutter app renders most content natively, but some activities have no
     * native screen (forums, and generic mod/url, mod/page, mod/book, mod/lesson,
     * mod/scorm, etc.) so it opens the real Moodle page inside a WebView. In that
     * WebView the student must ONLY see the activity material — no site header,
     * footer, breadcrumbs, nav drawer, user/settings menu, activity prev/next,
     * "back to course" or login/logout links — nothing that lets them roam the
     * wider site or leave the activity.
     *
     * Moodle already ships exactly such a stripped layout: `embedded` (used for
     * iframes/filepickers). Switching the whole app WebView session to it gives us
     * chrome-free rendering server-side, so the app needs no CSS/JS injection.
     *
     * This runs on `before_http_headers`, which core dispatches at the very top of
     * core_renderer::header() BEFORE the page state moves to PRINTING_HEADER and
     * BEFORE the layout file is resolved — so set_pagelayout() here still takes
     * effect. Desktop/browser users are never affected: the detection below only
     * matches the app.
     *
     * Detection (any one is enough):
     *   1. `core_useragent::is_moodle_app()` — the WebView sends a User-Agent
     *      containing "MoodleMobile" (recommended; same signal the app already
     *      uses on its web-service calls). This is the cleanest single trigger.
     *   2. `?nitembed=1` on the URL — a fallback the app can append to the
     *      autologin `urltogo` target if it cannot change the WebView UA.
     *   3. A session flag we set on the first match — because in-activity links
     *      generated by core (e.g. a forum discussion, a reply form) carry
     *      neither signal, but they ARE loaded in the same isolated WebView
     *      session, so the flag keeps them chrome-free too.
     *
     * The session flag lives only in the WebView's own cookie jar / session, which
     * is separate from any desktop browser session, so it can never leak the
     * embedded layout to web users.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE, $SESSION;

        if (empty($PAGE)) {
            return;
        }

        // Never touch layouts that are already chrome-free or must not be altered.
        $skiplayouts = ['embedded', 'maintenance', 'redirect', 'print'];
        if (in_array($PAGE->pagelayout, $skiplayouts, true)) {
            return;
        }

        // Is this the app's WebView? Sticky for the rest of the (WebView-only) session.
        $isapp = !empty($SESSION->theme_nit_appembed)
            || \core_useragent::is_moodle_app()
            || (bool) optional_param('nitembed', 0, PARAM_BOOL);

        if (!$isapp) {
            return;
        }

        // Remember, so subsequent in-activity page loads stay chrome-free even
        // when they carry neither the app User-Agent nor the ?nitembed param.
        $SESSION->theme_nit_appembed = 1;

        $PAGE->set_pagelayout('embedded');
    }

    /**
     * Load the sign-up password affordances: a strength meter and a reveal
     * ("eye") toggle on the password box.
     *
     * Why a hook and not the template
     * -------------------------------
     * theme_nit already overrides core/signup_form_layout, but a Mustache
     * template cannot read $CFG. The meter has to know the site's *configured*
     * password policy (Site administration > Security > Site security settings)
     * so it never reports "strong" for something check_password_policy() will
     * reject on submit — the bar is an affordance, the server stays the gate.
     * So the policy and the labels are handed to the module from here.
     *
     * `before_footer_html_generation` is the right moment: the page is still
     * open, so js_call_amd() lands in the footer script block, and the password
     * box is guaranteed to be in the DOM by the time the module runs.
     *
     * Scoped to the sign-up page only. Nothing is injected anywhere else, and
     * with JS off the form degrades to exactly what core renders today.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        global $CFG, $PAGE;

        if (empty($PAGE)) {
            return;
        }

        self::load_form_gate();
        self::load_inline_validation();
        self::load_either_or();

        // AC-4.4.1 asks for the same reveal toggle and the same per-rule messages
        // on the "set a new password" screen as on sign-up. The module finds the
        // password box by element type, so the only thing these screens needed was
        // to be told to load it.
        $passwordpages = [
            'login-signup',
            'login-forgot_password',
            'login-change_password',
            'local-profilefields-complete',
        ];

        if (!in_array($PAGE->pagetype, $passwordpages, true)) {
            return;
        }

        $policy = self::password_policy();

        $strings = [
            'strength'     => get_string('passwordstrength', 'theme_nit'),
            'showpassword' => get_string('showpassword', 'theme_nit'),
            'hidepassword' => get_string('hidepassword', 'theme_nit'),
            // Keyed 1..4 to match the score the module produces.
            'levels'       => [
                1 => get_string('passwordstrengthweak', 'theme_nit'),
                2 => get_string('passwordstrengthfair', 'theme_nit'),
                3 => get_string('passwordstrengthgood', 'theme_nit'),
                4 => get_string('passwordstrengthstrong', 'theme_nit'),
            ],
        ];

        $PAGE->requires->js_call_amd('theme_nit/passwordstrength', 'init', [
            ['policy' => $policy, 'strings' => $strings],
        ]);
    }

    /**
     * Say why the forgotten-password form has locked one of its two boxes.
     *
     * Core disables "Email address" while "Username" holds a value, and the
     * other way round (`disabledIf` in login/forgot_password_form.php), and it
     * does so on blur - so the click that leaves one box is the click that lands
     * on a box that has just stopped taking input, with nothing on the page to
     * say why. theme_nit/eitheror prints the reason under the locked box and a
     * button that clears the other one. The rule itself is untouched.
     *
     * @return void
     */
    protected static function load_either_or(): void {
        global $PAGE;

        if ($PAGE->pagetype !== 'login-forgot_password') {
            return;
        }

        $PAGE->requires->js_call_amd('theme_nit/eitheror', 'init', [
            [
                'form' => '#region-main form',
                // The browser's autofill fills BOTH boxes from one saved login.
                // The e-mail is the one that stands then: the site logs in by
                // e-mail, so that is what the browser saved, and what it wrote
                // into the username box is that same address.
                'keep' => 'email',
                'pairs' => [
                    [
                        'name' => 'email',
                        'lockedBy' => 'username',
                        'strings' => [
                            'locked' => get_string('eitherorlockedbyusername', 'theme_nit'),
                            'clear' => get_string('eitherorclearusername', 'theme_nit'),
                        ],
                    ],
                    [
                        'name' => 'username',
                        'lockedBy' => 'email',
                        'strings' => [
                            'locked' => get_string('eitherorlockedbyemail', 'theme_nit'),
                            'clear' => get_string('eitherorclearemail', 'theme_nit'),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Hand the inline-validation module the server's own sentences (US-4.1.3).
     *
     * The messages are fetched here rather than written into the JavaScript so
     * that there is exactly one place they exist. AC-4.1.15 fixes the wording and
     * acceptance is tested on it; a second copy in a .js file would drift from the
     * first the moment either is edited, and the drift would show as the field
     * saying one thing before submit and another after.
     *
     * Runs on the same screens as the button gate, and only when
     * local_profilefields is present to supply the strings.
     *
     * @return void
     */
    protected static function load_inline_validation(): void {
        global $PAGE;

        if (!get_config('local_profilefields', 'gatebuttons')) {
            return;
        }

        $pages = self::gated_pages();
        if (!isset($pages[$PAGE->pagetype])) {
            return;
        }
        if (!get_string_manager()->string_exists('errfirstnameempty', 'local_profilefields')) {
            return;
        }

        $keys = [
            'errfirstnameempty', 'errlastnameempty', 'errnamelength', 'errnamechars',
            'erremailempty', 'erremailformat', 'errphoneempty', 'errphonedigits',
            'pwtooshort', 'pwnoupper', 'pwnolower', 'pwnodigit',
        ];

        $strings = [];
        foreach ($keys as $key) {
            $strings[$key] = get_string($key, 'local_profilefields');
        }

        $PAGE->requires->js_call_amd('theme_nit/inlinevalidation', 'init', [
            [
                'forms' => explode(', ', $pages[$PAGE->pagetype]),
                'strings' => $strings,
            ],
        ]);
    }

    /**
     * The minimums the strength meter should score against.
     *
     * Not simply `$CFG->minpassword*`. local_profilefields moves the whole rule
     * set into its own `check_password_policy()` function and zeroes core's
     * minimums, because AC-4.1.6 wants one message in the specification's wording
     * where core would print several in its own. Reading $CFG here would therefore
     * show the learner a meter with no floor at all, cheerfully calling "abc"
     * acceptable right up until the server refused it.
     *
     * So: when that plugin is in charge, mirror its rules. Otherwise fall back to
     * whatever core is configured with, which is what a site without the plugin is
     * actually enforcing.
     *
     * @return array{minlength: int, digits: int, lower: int, upper: int, nonalphanum: int}
     */
    protected static function password_policy(): array {
        global $CFG;

        if (class_exists('\local_profilefields\validation')) {
            return [
                'minlength'   => \local_profilefields\validation::PASSWORD_MIN,
                'digits'      => 1,
                'lower'       => 1,
                'upper'       => 1,
                // AC-4.1.6 lists four rules and a symbol is not among them.
                'nonalphanum' => 0,
            ];
        }

        // With the policy switched off there is no floor to clear, so the meter
        // scores purely on length and variety.
        return [
            'minlength'   => empty($CFG->passwordpolicy) ? 0 : (int) $CFG->minpasswordlength,
            'digits'      => empty($CFG->passwordpolicy) ? 0 : (int) $CFG->minpassworddigits,
            'lower'       => empty($CFG->passwordpolicy) ? 0 : (int) $CFG->minpasswordlower,
            'upper'       => empty($CFG->passwordpolicy) ? 0 : (int) $CFG->minpasswordupper,
            'nonalphanum' => empty($CFG->passwordpolicy) ? 0 : (int) $CFG->minpasswordnonalphanum,
        ];
    }

    /**
     * The screens whose submit button waits for a complete form, and the form on
     * each of them.
     *
     * AC-4.1.1 asks for this on the sign-up screen. It was extended to the rest
     * of the academy's own journey - the same learner meeting the same behaviour
     * everywhere is worth more than one screen behaving specially - but it stops
     * there, deliberately.
     *
     * What is NOT in this list is the point of it. Moodle's administrative and
     * authoring forms (course settings, the question bank, activity editing) are
     * full of fields that are marked required while a condition hides them, and a
     * gate on those would leave an administrator with a dead button and nothing to
     * read. That failure would not show up in testing; it would show up months
     * later, to the one person who cannot work around it.
     *
     * A plugin form of ours can also opt in without appearing here, by carrying
     * `data-nit-gate` in its own markup - which is the better route whenever the
     * markup is ours to change.
     *
     * @return array<string, string> page type => CSS selector for the form on it
     */
    protected static function gated_pages(): array {
        return [
            // Sign-up (AC-4.1.1) and the Google/Apple completion of it (AC-4.3.9).
            'login-signup'                    => '#page-content form, .signupform form',
            'local-profilefields-complete'    => '#region-main form',
            // Login, and the two steps of a password reset.
            'login-index'                     => '#login form, form#login',
            'login-forgot_password'           => '#region-main form',
            'login-change_password'           => '#region-main form',
            // The learner's own profile.
            'user-edit'                       => '#region-main form',
            'user-editadvanced'               => '#region-main form',
            // Checkout.
            'local-payments-checkout'         => '#region-main form, form.nit-checkout',
        ];
    }

    /**
     * Hand the form-gate module its instructions, on the pages that want it.
     *
     * Switched off by default from the sign-up settings page, so that a site
     * meeting a form we have not anticipated can turn the behaviour off in one
     * click rather than waiting for a deployment.
     *
     * @return void
     */
    protected static function load_form_gate(): void {
        global $PAGE;

        if (!get_config('local_profilefields', 'gatebuttons')) {
            return;
        }

        $pages = self::gated_pages();
        if (!isset($pages[$PAGE->pagetype])) {
            return;
        }

        $PAGE->requires->js_call_amd('theme_nit/formgate', 'init', [
            [
                'forms' => explode(', ', $pages[$PAGE->pagetype]),
                // The Terms and Conditions box carries no required rule - it is an
                // advcheckbox validated server-side in local_profilefields - so the
                // module cannot find it the way it finds the rest. AC-4.1.1 names it
                // explicitly, so it is named explicitly here.
                'extraRequired' => [
                    'input[name="localprofilefieldsconsent"]',
                    'input[name="policyagreed"]',
                ],
                'hint' => get_string('gatehint', 'theme_nit'),
            ],
        ]);
    }

    /**
     * Carry the visitor's light/dark choice onto the <html> element.
     *
     * The switch does not own a palette: it selects one of the three
     * Brand-Colors groups (mapped mode → group on the gallery "Change style"
     * tab), so re-skinning the site is just a matter of putting that group's
     * switch class somewhere every rule can see it.
     *
     * <html> — not <body> — is that place. The group switch works by re-pointing
     * the `--nit-brand-*` custom properties, and scss/foundation/_root.scss
     * aliases the legacy `--nit-*` properties on `:root`, i.e. on <html> itself.
     * A custom property holding a var() is substituted on the element that
     * declares it, so those aliases would freeze to Group 1 if the switch class
     * sat any lower in the tree, and the page would recolour only half-way.
     *
     * Rendered server-side rather than left to the browser so the first paint is
     * already in the chosen mode — a class added by JS after load would flash the
     * other palette on every page.
     *
     * @param \core\hook\output\before_html_attributes $hook
     */
    public static function before_html_attributes(\core\hook\output\before_html_attributes $hook): void {
        global $CFG;

        require_once($CFG->dirroot . '/theme/nit/lib.php');

        $classes = theme_nit_mode_classes();
        if ($classes === '') {
            return;
        }

        // Another plugin may already have put a class here; append rather than
        // replace (add_attribute() overwrites the key outright).
        $existing = trim((string) ($hook->get_attributes()['class'] ?? ''));
        $hook->add_attribute('class', trim($existing . ' ' . $classes));
    }
}
