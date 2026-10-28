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

namespace local_appleauth;

/**
 * Sign in with Apple on the website.
 *
 * web_login.php sends the visitor to Apple's authorize page; Apple POSTs the
 * identity token back to web_callback.php (response_mode=form_post — Apple's only
 * mode when the name / e-mail are asked for), which signs the visitor in.
 *
 * Core's OAuth 2 services cannot do this: Apple has no userinfo endpoint, answers
 * with a POST, and its client secret is a JWT that expires within six months. The
 * identity token alone is enough to sign in, so no secret or key is involved.
 *
 * The request is bound to the browser that started it by a one-time state, kept
 * server side with its nonce and the page to return to, and in a cookie that has
 * to come back with Apple's POST: a state alone would let someone sign a visitor
 * into the attacker's account.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class web {

    /** Apple's authorize page. */
    const AUTHORIZE_URL = 'https://appleid.apple.com/auth/authorize';

    /** How long the visitor has to finish on Apple's side. */
    const STATE_TTL = 10 * MINSECS;

    /**
     * Is the web button on: the plugin enabled and a Services ID set.
     *
     * @return bool
     */
    public static function enabled(): bool {
        $config = get_config('local_appleauth');
        return !empty($config->enabled) && self::client_id() !== '';
    }

    /**
     * The Services ID the website signs in with.
     *
     * @return string
     */
    public static function client_id(): string {
        return trim((string) get_config('local_appleauth', 'webclientid'));
    }

    /**
     * Where Apple sends the visitor back (the Return URL registered on the Services ID).
     *
     * @return \moodle_url
     */
    public static function callback_url(): \moodle_url {
        return new \moodle_url('/local/appleauth/web_callback.php');
    }

    /**
     * The button for the log-in and sign-up pages, in the shape of core's
     * prepared identity providers, or null when web sign-in is off.
     *
     * The logo is a mask (`maskiconurl`), painted in the button's own text colour,
     * so it follows the brand colours in light and dark; Apple's logo is single-colour.
     *
     * @param \renderer_base $output
     * @return array|null {url, name, iconurl, maskiconurl}
     */
    public static function identity_provider(\renderer_base $output): ?array {
        if (!self::enabled()) {
            return null;
        }
        return [
            'url' => (new \moodle_url('/local/appleauth/web_login.php'))->out(false),
            'name' => 'Apple',
            'iconurl' => '',
            'maskiconurl' => $output->image_url('apple', 'local_appleauth')->out(false),
        ];
    }

    /**
     * Start a sign-in: remember the request and return Apple's authorize URL.
     *
     * @param string $wantsurl the page to land on afterwards ('' for the default)
     * @return \moodle_url
     */
    public static function authorize_url(string $wantsurl): \moodle_url {
        $state = random_string(32);
        $nonce = random_string(32);
        \cache::make('local_appleauth', 'webstate')->set($state, [
            'nonce' => $nonce,
            'wantsurl' => $wantsurl,
            'expires' => time() + self::STATE_TTL,
        ]);
        self::set_state_cookie($state, time() + self::STATE_TTL);

        return new \moodle_url(self::AUTHORIZE_URL, [
            'client_id' => self::client_id(),
            'redirect_uri' => self::callback_url()->out(false),
            'response_type' => 'code id_token',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Take back a started request: one use, unexpired, from the browser that began it.
     *
     * @param string $state what Apple posted back
     * @return array {nonce, wantsurl}
     * @throws auth_error invalid_state
     */
    public static function take_state(string $state): array {
        $cache = \cache::make('local_appleauth', 'webstate');
        $entry = ($state !== '' && preg_match('/^[a-zA-Z0-9]{32}$/', $state)) ? $cache->get($state) : false;
        if ($entry !== false) {
            $cache->delete($state);
        }
        $cookie = (string) ($_COOKIE[self::cookie_name()] ?? '');
        self::set_state_cookie('', 1);

        if (!is_array($entry) || $entry['expires'] < time() || !hash_equals($state, $cookie)) {
            throw new auth_error('invalid_state', 400);
        }
        return $entry;
    }

    /**
     * Name of the cookie holding the state, per site like the session cookie.
     *
     * @return string
     */
    private static function cookie_name(): string {
        global $CFG;
        return 'MoodleAppleState' . ($CFG->sessioncookie ?? '');
    }

    /**
     * Set (or clear) the state cookie. It must ride on Apple's cross-site POST,
     * which takes SameSite=None, which browsers accept only on a secure cookie.
     *
     * @param string $value
     * @param int $expires
     */
    private static function set_state_cookie(string $value, int $expires): void {
        global $CFG;
        $path = rtrim((string) parse_url($CFG->wwwroot, PHP_URL_PATH), '/') . '/local/appleauth/';
        setcookie(self::cookie_name(), $value, [
            'expires' => $expires,
            'path' => $path,
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => is_https() ? 'None' : 'Lax',
        ]);
    }

    /**
     * What to tell the visitor on the log-in page when a web sign-in fails.
     *
     * @param string $errorcode an auth_error code
     * @return string
     */
    public static function error_message(string $errorcode): string {
        $known = ['auth_not_linkable', 'user_not_found', 'user_suspended', 'user_not_confirmed',
            'email_not_unique', 'site_maintenance', 'apple_unavailable'];
        return get_string(in_array($errorcode, $known, true) ? 'weberror_' . $errorcode : 'weberror_generic',
            'local_appleauth');
    }
}
