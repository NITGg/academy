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

namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Sign a user out of everything, everywhere.
 *
 * AC-4.3.10 / AC-4.5.2 / AC-4.4.7 want one guarantee: after a password changes,
 * no device keeps the access it had under the old one. AC-4.24.4 wants the same
 * guarantee the moment an account is blocked. "Everywhere" is two stores, and
 * Moodle only clears one of them:
 *
 * - **Browser sessions** live in the sessions table. Core destroys them on most
 *   of its own password paths, so the site half usually works already.
 * - **Web-service tokens** live in external_tokens, and core only deletes them
 *   when `$CFG->passwordchangetokendeletion` is on (off by default) or when an
 *   administrator happens to tick "sign out of other services" on the edit form.
 *   Leave that to chance and the app keeps working on the old token after the
 *   password was changed from the website - the exact hole these ACs describe.
 *
 * So every token row for the user goes, for every service, **including the token
 * the request came in on**. An app changing its own password logs itself out
 * locally anyway, and sparing that one row would mean the one device that
 * definitely knows the password changed is the one device we let carry on.
 *
 * How the other devices find out is deliberately boring:
 *
 * - `/webservice/rest/server.php` answers a deleted token with
 *   `errorcode: "invalidtoken"`, which the app already handles.
 * - our own token endpoints (`/local/academy/api.php` and friends) answer with
 *   HTTP 401, which the app's generic 401 handler already handles.
 *
 * Nothing has to be pushed to a device; the next call it makes tells it.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_terminator {

    /**
     * Who has already been signed out during this request, and what was spared.
     *
     * update_internal_user_password() can fire user_password_updated twice in one
     * request - once when an out-of-date hash is silently upgraded, once for the
     * real change - and the app's change-password call verifies the old password
     * before setting the new one, which is exactly that shape. The second pass
     * would find nothing left to delete, so skipping it is about not repeating
     * the work rather than about correctness.
     *
     * What is stored is the session id that pass spared, so a later pass that
     * needs to spare *less* is not skipped: a request that changes a password
     * (which keeps the acting browser) and then blocks the account (which keeps
     * nothing) must still take that last session away.
     *
     * @var array<int, string|null> userid => the sid spared, or null if none was
     */
    private static array $done = [];

    /**
     * Sign the user out of every browser session and every web-service token.
     *
     * @param int $userid the account to sign out
     * @param string|null $keepsid a browser session id to spare, or null to spare none
     * @return void
     */
    public static function terminate(int $userid, ?string $keepsid = null): void {
        global $CFG;

        if ($userid <= 0) {
            return;
        }
        // Already done this request, and that pass spared no more than this one
        // asks to spare - so there is nothing left for this call to take.
        if (array_key_exists($userid, self::$done)
                && (self::$done[$userid] === null || self::$done[$userid] === $keepsid)) {
            return;
        }
        self::$done[$userid] = $keepsid;

        // Browser sessions. kill_user_sessions() is the name AC-4.3.10 quotes; it
        // has been a deprecated alias for this since Moodle 4.5 (MDL-66161) and
        // calling it would emit a deprecation notice on every password change.
        \core\session\manager::destroy_user_sessions($userid, $keepsid);

        // Web-service tokens - every service, every token type, no exceptions.
        // webservice/lib.php is not autoloaded, hence the require here rather
        // than at the top of the file: a class file is included from inside the
        // autoloader, where $CFG is not in scope.
        require_once($CFG->dirroot . '/webservice/lib.php');
        \webservice::delete_user_ws_tokens($userid);
    }

    /**
     * Sign the user out after their password changed.
     *
     * The one session spared is the browser the change was made in, when the
     * person making it is the account being changed: someone who has just typed
     * their new password into the profile screen has proved they know it, and
     * bouncing them to the login page mid-flow would be a worse site than the one
     * we started with. This mirrors what core's own change_password.php does, so
     * the web half behaves identically whether or not this plugin is installed.
     *
     * Every other device - other browsers, and every app install, since a token
     * is not a session and is never spared - has to sign in again.
     *
     * A token-authenticated request has no browser session of its own
     * (NO_MOODLE_COOKIES), so session_id() is empty there and nothing is spared:
     * the app's own token goes too, which is what AC-4.5.2 asks for.
     *
     * @param int $userid the account whose password changed
     * @return void
     */
    public static function password_changed(int $userid): void {
        global $USER;

        $keepsid = null;
        if (!empty($USER->id) && (int) $USER->id === $userid) {
            $sid = session_id();
            $keepsid = ($sid === '') ? null : $sid;
        }

        self::terminate($userid, $keepsid);
    }

    /**
     * Sign the user out because the account was blocked.
     *
     * AC-4.24.4: "Blocking takes effect immediately: active sessions are
     * terminated." Nothing is spared here - a blocked account keeps no access
     * anywhere, including in whatever window it is currently sitting in.
     *
     * @param int $userid the account that was blocked
     * @return void
     */
    public static function blocked(int $userid): void {
        self::terminate($userid, null);
    }
}
