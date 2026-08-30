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

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * "Remember me": a signed-in device that survives the session expiring.
 *
 * AC-4.3.5 asks for two different lifetimes - 24 hours of inactivity normally,
 * 30 days with Remember Me - and Moodle cannot express that. `$CFG->sessiontimeout`
 * is site-wide, and the `sessions` table has no per-row lifetime column, so there
 * is no supported way to give one user a longer session than another.
 *
 * So the session is left alone at 24 hours and a second, independent credential
 * is kept beside it. When the session has gone but the cookie is still good, a
 * fresh session is built from it silently. The learner experiences one long
 * login; the server never has a session outliving its configured lifetime.
 *
 * Design notes, each of which is load-bearing:
 *
 * **Selector and validator.** The cookie is `selector:validator`. The selector is
 * indexed and looked up; the validator is compared against a SHA-256 hash. This
 * is the standard split, and it buys two things: a stolen database contains no
 * usable token, and the lookup is a single indexed equality rather than a scan
 * comparing secrets - so it cannot be timed to discover a valid selector.
 *
 * **Single use.** Every successful sign-in destroys the token and issues a new
 * one. That is what makes theft detectable: if an attacker uses a stolen cookie,
 * the legitimate browser's copy is now stale, and presenting a stale validator
 * for a live selector is a signal that two parties hold the same token. That case
 * revokes every token for the account and emails the owner. A token that could be
 * replayed indefinitely would give none of this away.
 *
 * **Not a substitute for authentication.** The token restores an ordinary
 * session, and no more. It never elevates: `\core\session\manager::is_loggedinas()`
 * and the site administrator check keep it away from anything that matters, and a
 * password change destroys every token the account has.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rememberme {

    /** @var string Table holding one row per trusted device. */
    const TABLE = 'local_profilefields_remember';

    /** @var string The cookie name. Prefixed like Moodle's own cookies. */
    const COOKIE = 'nitremember';

    /** @var int Bytes of randomness in each half of the token. */
    const BYTES = 16;

    /**
     * Is the feature switched on?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config(manager::COMPONENT, 'remembermeenabled');
    }

    /**
     * How long a newly issued token lives, in seconds.
     *
     * @return int
     */
    public static function lifetime(): int {
        return ((int) (get_config(manager::COMPONENT, 'remembermedays') ?: 30)) * DAYSECS;
    }

    /**
     * Issue a token to this browser and set the cookie.
     *
     * Called when a learner logs in with the box ticked.
     *
     * @param stdClass $user the account to remember
     * @return void
     */
    public static function remember(stdClass $user): void {
        global $DB;

        if (!self::enabled() || isguestuser($user)) {
            return;
        }

        // Never for an administrator. A 30-day cookie that reaches site
        // configuration is a different risk from one that reaches a course, and
        // the specification asks for the second.
        if (is_siteadmin($user)) {
            return;
        }

        $selector = random_string(32);
        $validator = bin2hex(random_bytes(self::BYTES));

        $DB->insert_record(self::TABLE, (object) [
            'userid' => $user->id,
            'selector' => $selector,
            'validator' => hash('sha256', $validator),
            'useragent' => self::agent_hash(),
            'lastip' => getremoteaddr('', 45),
            'expires' => time() + self::lifetime(),
            'timecreated' => time(),
        ]);

        self::set_cookie($selector . ':' . $validator, time() + self::lifetime());
    }

    /**
     * Try to sign in from the cookie. Returns the account, or null.
     *
     * The caller is responsible for actually establishing the session - this
     * decides only whether the cookie earns one, and rotates the token when it
     * does.
     *
     * @return stdClass|null the user to log in, or null when the cookie buys nothing
     */
    public static function resume(): ?stdClass {
        global $DB;

        if (!self::enabled()) {
            return null;
        }

        $cookie = self::read_cookie();
        if ($cookie === null) {
            return null;
        }

        [$selector, $validator] = $cookie;

        $row = $DB->get_record(self::TABLE, ['selector' => $selector]);
        if (!$row) {
            // No such token. Either it was already rotated away or the cookie was
            // invented; both mean the cookie is worthless, so drop it.
            self::forget_cookie();
            return null;
        }

        if ($row->expires < time()) {
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
            self::forget_cookie();
            return null;
        }

        // A live selector with the wrong validator is the theft signal described
        // in the class comment: two parties are holding tokens derived from the
        // same row, and only one of them can be the owner. Revoke everything and
        // tell the account holder.
        if (!hash_equals($row->validator, hash('sha256', $validator))) {
            self::revoke_all((int) $row->userid);
            self::forget_cookie();
            self::warn_owner((int) $row->userid);
            return null;
        }

        // A token issued to one browser must not work in another. This is a weak
        // signal - user agents change with every browser update - which is why a
        // mismatch simply refuses this token rather than raising the alarm.
        if ($row->useragent !== self::agent_hash()) {
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
            self::forget_cookie();
            return null;
        }

        $user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0]);

        // Suspended, unconfirmed, or an account that has since become an
        // administrator: all refused, and the token destroyed rather than left to
        // be tried again on the next page.
        if (!$user || !empty($user->suspended) || empty($user->confirmed) || is_siteadmin($user)) {
            self::revoke_all((int) $row->userid);
            self::forget_cookie();
            return null;
        }

        // Single use - but only when we can actually hand the browser its
        // replacement. Rotating consists of deleting this row and setting a new
        // cookie, and if the second half cannot happen the browser is left holding
        // a token whose row is gone. On its next visit that reads as a stale
        // validator against a live selector, which is precisely the theft signal
        // above: every device revoked and a security email sent, to a learner who
        // did nothing but load a page.
        //
        // So when the headers have already gone out, the token is honoured and
        // left unspent, and rotation waits for the next request. That is a token
        // used twice in a rare edge case, against wrongly locking someone out of
        // every device they own - an easy trade to make.
        if (!headers_sent()) {
            $DB->delete_records(self::TABLE, ['id' => $row->id]);
            self::remember($user);
        }

        return $user;
    }

    /**
     * Destroy the token this browser holds, and its cookie.
     *
     * Called on logout: "keep me signed in" is a statement about this device, and
     * signing out of the device withdraws it.
     *
     * @return void
     */
    public static function forget(): void {
        global $DB;

        $cookie = self::read_cookie();
        if ($cookie !== null) {
            $DB->delete_records(self::TABLE, ['selector' => $cookie[0]]);
        }

        self::forget_cookie();
    }

    /**
     * Destroy every token an account holds, on every device.
     *
     * The blunt instrument, used whenever the account's credentials change or its
     * standing does: a password change or reset (AC-4.4.7), suspension, deletion,
     * and the theft signal above.
     *
     * @param int $userid
     * @return void
     */
    public static function revoke_all(int $userid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }

    /**
     * Remove tokens that have expired.
     *
     * Expiry is enforced on read, so this is housekeeping rather than a control -
     * it keeps the table proportional to the number of live devices instead of to
     * the number of logins the site has ever had.
     *
     * @return int rows removed
     */
    public static function purge_expired(): int {
        global $DB;

        $count = $DB->count_records_select(self::TABLE, 'expires < ?', [time()]);
        $DB->delete_records_select(self::TABLE, 'expires < ?', [time()]);

        return $count;
    }

    /**
     * Tell an account holder that a stale token was presented for their account.
     *
     * Sent unconditionally rather than through the message API: this is a security
     * notice, and AC-4.5.5 puts those beyond the reach of the preference screen.
     *
     * @param int $userid
     * @return void
     */
    protected static function warn_owner(int $userid): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user || isguestuser($user)) {
            return;
        }

        $site = get_site();
        $data = (object) [
            'firstname' => $user->firstname,
            'sitename' => format_string($site->fullname),
        ];

        email_to_user(
            $user,
            \core_user::get_support_user(),
            get_string('remembermestolen', 'local_profilefields', format_string($site->fullname)),
            get_string('remembermestolenbody', 'local_profilefields', $data)
        );
    }

    /**
     * A short, stable fingerprint of the requesting browser.
     *
     * Truncated to 32 hex characters: the column is 64 wide, and half a SHA-256 is
     * far past the point where a collision between two user-agent strings is worth
     * thinking about.
     *
     * @return string
     */
    protected static function agent_hash(): string {
        return substr(hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 32);
    }

    /**
     * The cookie split into its two halves, or null when there is no usable one.
     *
     * @return array{0: string, 1: string}|null
     */
    protected static function read_cookie(): ?array {
        $raw = (string) ($_COOKIE[self::cookie_name()] ?? '');
        if ($raw === '' || substr_count($raw, ':') !== 1) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);

        // Both halves are generated from a fixed alphabet at a fixed length, so
        // anything else was not made here and is not worth a database round trip.
        if (!preg_match('/^[A-Za-z0-9]{32}$/', $selector)
                || !preg_match('/^[0-9a-f]{' . (self::BYTES * 2) . '}$/', $validator)) {
            return null;
        }

        return [$selector, $validator];
    }

    /**
     * The cookie name, sharing the site's configured cookie prefix.
     *
     * @return string
     */
    protected static function cookie_name(): string {
        global $CFG;

        return ($CFG->sessioncookie ?? '') . self::COOKIE;
    }

    /**
     * Write the cookie, with the flags a long-lived credential needs.
     *
     * `HttpOnly` keeps it away from script, so an XSS bug cannot read it.
     * `SameSite=Lax` means it is not sent on a cross-site POST, which is what
     * keeps it from being used to mount a login as somebody else. `Secure`
     * follows the site's own cookie configuration rather than being forced,
     * because a development site on plain HTTP would otherwise never see it.
     *
     * @param string $value the cookie value, or '' to delete it
     * @param int $expires unix time the cookie should die
     * @return void
     */
    protected static function set_cookie(string $value, int $expires): void {
        global $CFG;

        if (headers_sent()) {
            // Nothing to be done; the next page will issue it instead.
            return;
        }

        setcookie(self::cookie_name(), $value, [
            'expires' => $expires,
            'path' => $CFG->sessioncookiepath ?? '/',
            'domain' => $CFG->sessioncookiedomain ?? '',
            'secure' => !empty($CFG->cookiesecure),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // So that code later in this same request sees the change too.
        if ($value === '') {
            unset($_COOKIE[self::cookie_name()]);
        } else {
            $_COOKIE[self::cookie_name()] = $value;
        }
    }

    /**
     * Delete the cookie from this browser.
     *
     * @return void
     */
    protected static function forget_cookie(): void {
        self::set_cookie('', time() - DAYSECS);
    }
}
