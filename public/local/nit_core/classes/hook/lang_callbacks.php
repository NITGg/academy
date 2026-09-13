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

namespace local_nit_core\hook;

use core\hook\after_config;

/**
 * Language hook callbacks — keep the site in the language the visitor last used.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lang_callbacks {
    /**
     * The cookie the last-used interface language is remembered in.
     *
     * A cookie, because it is the only thing that survives both ends of a session: logging
     * out empties $SESSION (\core\session\manager::terminate_current()) and logging in unsets
     * $SESSION->lang (set_login_session_preferences()), so anything kept in the session is
     * gone at exactly the two moments this has to be read back. Same shape, and same reason,
     * as theme_nit's `nit_mode` cookie for the light/dark switch.
     */
    public const COOKIE = 'nit_lang';

    /** How long the cookie lives — re-issued whenever the language changes. */
    public const COOKIE_LIFETIME = YEARSECS;

    /**
     * $SESSION key: the signed-in user's id and profile language as last seen, so the next
     * request can tell that the profile language was changed (see keep_last_used_language()).
     */
    public const PROFILE_KEY = 'local_nit_core_profilelang';

    /**
     * Keep the site in the language the visitor last used, across log-in and log-out.
     *
     * Moodle resolves the interface language as $SESSION->lang, then the profile language,
     * then the site default — and throws $SESSION->lang away at both ends of a session:
     * set_login_session_preferences() unsets it on every login and terminate_current()
     * empties the whole session on logout. So a visitor reading the site in Arabic got
     * English the moment they logged out (the site default), and a learner who had switched
     * to English got Arabic back the moment they logged in (the profile language). The rule
     * here is that neither logging in nor logging out changes the language: the site stays
     * in whatever the visitor last used, and only two things move it — the navbar switcher
     * (?lang=xx) and the preferred-language setting on the account screen.
     *
     * Three steps, on every page request:
     *
     *  1. A signed-in user whose profile language changed since the previous request (the
     *     account screen, /user/language.php and /user/edit.php all refresh $USER after
     *     saving) has just chosen that language: drop $SESSION->lang so the profile is what
     *     shows, and let step 3 record it. Without this the session language put back in
     *     step 2 would sit on top of the new setting for good — there is no longer a login
     *     to clear it, which is the one thing that used to.
     *
     *  2. Put the remembered language back whenever the session is not showing it: the first
     *     request after a login or a logout, but also a fresh session in which
     *     setup_lang_from_browser() guessed from the browser. Not when this request is a
     *     ?lang=xx click — that is the visitor changing their mind; setup.php has applied it
     *     and step 3 records it.
     *
     *  3. Record the language in the cookie when it differs from what the cookie holds and
     *     it is one the visitor actually chose: a ?lang=xx click, a changed profile language,
     *     or any page a signed-in user reads (their language is the profile or a click, both
     *     deliberate). A logged-out visitor's default or browser-detected language is NOT
     *     recorded: a learner whose profile says Arabic and who opens the log-in page from an
     *     English-looking browser without touching the switcher still gets Arabic on that
     *     first login — the profile is the fallback for a visitor with no history.
     *
     * Runs on after_config: dispatched from setup.php just after ?lang=xx has been turned
     * into $SESSION->lang and setup_lang_from_browser() has run, and before anything asks
     * current_language() what to render in. A course-forced language sits above all of this
     * in current_language() and is untouched.
     *
     * @param after_config $hook the configuration hook (unused; the request is the input)
     * @return void
     */
    public static function keep_last_used_language(after_config $hook): void {
        global $SESSION, $USER;

        // Nothing to keep in step where there are no cookies (CLI, web services, PHPUnit,
        // the cookie-less endpoints such as javascript.php and styles.php) and nothing to
        // gain in AJAX calls — they run inside a session a page request has already set up.
        if (NO_MOODLE_COOKIES || AJAX_SCRIPT || during_initial_install()) {
            return;
        }

        $userid = (int) ($USER->id ?? 0);
        $signedin = $userid > 0 && !isguestuser();
        $profilelang = (string) ($USER->lang ?? '');

        // 1. A changed profile language is the signed-in user choosing that language. Keyed
        // by user id so a login (0 → id) or a "log in as" (id → other id) only records.
        $adopted = false;
        $seen = $SESSION->{self::PROFILE_KEY} ?? null;
        $seenid = is_array($seen) ? (int) ($seen['id'] ?? 0) : -1;
        $seenlang = is_array($seen) ? (string) ($seen['lang'] ?? '') : '';
        if ($signedin && $seenid === $userid && $seenlang !== $profilelang && $profilelang !== '') {
            unset($SESSION->lang);
            $adopted = true;
        }
        if ($seenid !== $userid || $seenlang !== $profilelang) {
            $SESSION->{self::PROFILE_KEY} = ['id' => $userid, 'lang' => $profilelang];
        }

        $clicked = self::clicked_language();
        $remembered = self::remembered_language();

        // 2. Put the remembered language back when the session is not showing it.
        $changed = $adopted;
        if ($remembered !== '' && $clicked === '' && !$adopted && self::user_level_language() !== $remembered) {
            $SESSION->lang = $remembered;
            $changed = true;
        }

        // 3. Record a deliberate language.
        $current = self::user_level_language();
        if (($clicked !== '' || $adopted || $signedin) && $current !== $remembered
                && get_string_manager()->translation_exists($current, false)) {
            self::write_cookie($current);
        }

        if ($changed) {
            // setup.php has already reset the course-format session caches and called
            // moodle_setlocale() for the language it thought was current; redo both.
            \core_courseformat\base::session_cache_reset_all();
            moodle_setlocale();
        }
    }

    /**
     * The language the visitor is reading in, below anything a course forces.
     *
     * The tail of current_language() — session, then profile, then site — without the
     * forced-language and course/activity rungs: a course that forces Arabic is not the
     * visitor choosing Arabic, and $SESSION->forcelang is an administrator's debugging
     * override that is meant to last a session, not a year.
     *
     * @return string language code
     */
    protected static function user_level_language(): string {
        global $CFG, $SESSION, $USER;

        if (!empty($SESSION->lang)) {
            return (string) $SESSION->lang;
        }
        if (!empty($USER->lang)) {
            return (string) $USER->lang;
        }
        return (string) ($CFG->lang ?? 'en');
    }

    /**
     * The language this request switched to with ?lang=xx, or '' when it is not a switch.
     *
     * GET only and validated the way setup.php validates it, so this says "the switch took
     * effect" and not merely "a lang parameter was present". PARAM_SAFEDIR rather than
     * PARAM_LANG, as core uses there: PARAM_LANG blanks out an unknown code, which would
     * read as "no language asked for" instead of "asked for one we do not have".
     *
     * @return string language code or ''
     */
    protected static function clicked_language(): string {
        if (!isset($_GET['lang'])) {
            return '';
        }
        $lang = optional_param('lang', '', PARAM_SAFEDIR);
        if ($lang === '' || !get_string_manager()->translation_exists($lang, false)) {
            return '';
        }
        return $lang;
    }

    /**
     * The language the cookie remembers, or '' when there is none worth reading.
     *
     * A language pack that has since been uninstalled or disabled reads as no cookie at all,
     * so the next deliberate page rewrites it rather than the site trying to render in it.
     *
     * @return string language code or ''
     */
    protected static function remembered_language(): string {
        $lang = clean_param((string) ($_COOKIE[self::COOKIE] ?? ''), PARAM_SAFEDIR);
        if ($lang === '' || !get_string_manager()->translation_exists($lang, false)) {
            return '';
        }
        return $lang;
    }

    /**
     * Send the cookie, scoped exactly as core scopes its own.
     *
     * Path, domain, secure and httponly are the values \core\session\manager::prepare_cookies()
     * settled on for the session cookie, so this one is confined to this Moodle's path and
     * travels only where the session does.
     *
     * @param string $lang a validated language code
     * @return void
     */
    protected static function write_cookie(string $lang): void {
        global $CFG;

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $lang, [
            'expires'  => time() + self::COOKIE_LIFETIME,
            'path'     => (string) ($CFG->sessioncookiepath ?? '/'),
            'domain'   => (string) ($CFG->sessioncookiedomain ?? ''),
            'secure'   => is_moodle_cookie_secure(),
            'httponly' => !empty($CFG->cookiehttponly),
            'samesite' => 'Lax',
        ]);

        // So the rest of this request reads back what the browser will send next time.
        $_COOKIE[self::COOKIE] = $lang;
    }

    /**
     * Drop a leftover $SESSION->forcelang when the visitor explicitly asks for a language.
     *
     * current_language() reads $SESSION->forcelang before $SESSION->lang, so a session that
     * still carries a forced language ignores the navbar switcher entirely — the ?lang=xx link
     * sets $SESSION->lang in setup.php and nothing on screen changes. Sessions got into that
     * state through NIT AJAX endpoints that used to set forcelang and never put it back
     * (\local_nit_core\helper\lang::for_request now does), and those sessions stay stuck until
     * they are logged out, so clear the override here rather than waiting them out.
     *
     * Clicking a language link is an unambiguous request for that language, which makes
     * dropping the override the right reading and not merely the convenient one — it is what
     * core's own ?forcelang=none escape hatch does, minus having to know about it.
     *
     * Runs on after_config, dispatched from setup.php just after the block that turns ?lang=xx
     * into $SESSION->lang, and well before anything asks current_language() what to render in.
     *
     * @param after_config $hook the configuration hook (unused; the request is the input)
     * @return void
     */
    public static function after_config(after_config $hook): void {
        global $SESSION;

        // Nothing forced, nothing to clear.
        if (empty($SESSION->forcelang)) {
            return;
        }

        // GET only, matching how core reads ?lang= — a POSTed lang field is form data, not a
        // language switch, and must not silently retune the session.
        if (!isset($_GET['lang'])) {
            return;
        }

        // An explicit ?forcelang= in the same request is the visitor asking for the override.
        // Core has just honoured it a few lines above us; do not immediately undo it.
        if (isset($_GET['forcelang'])) {
            return;
        }

        // PARAM_SAFEDIR, as core uses here: PARAM_LANG blanks out an unknown code, so a typo
        // would read as "no language asked for" instead of "asked for something we don't have".
        $lang = optional_param('lang', '', PARAM_SAFEDIR);
        if ($lang === '' || !get_string_manager()->translation_exists($lang, false)) {
            return;
        }

        unset($SESSION->forcelang);

        // setup.php already called moodle_setlocale() for the language it thought was current.
        // That was the forced one, so redo it now that $SESSION->lang has the floor.
        moodle_setlocale();
    }
}
