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

namespace local_multitopics;

defined('MOODLE_INTERNAL') || die();

/**
 * The account behind the app's guest-browsing token.
 *
 * getsettings.php hands every app install one read-only token (built by
 * cli/app_guest_token.php) so the catalogue can be browsed before sign-in. To
 * Moodle that token is a signed-in user like any other — but the person holding
 * the phone is a visitor: there is no profile to read a country from, and nobody
 * to send to "set your country". Anything that treats visitors differently from
 * members — pricing, above all — asks here before trusting $USER->id.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class app_guest {

    /** @var int|null The account's id, memoised for the request; null until looked up. */
    private static $userid = null;

    /**
     * The guest-browsing account's id, or 0 when no token is published.
     *
     * The published token (local_multitopics/admin_token) is the one fact the app
     * and the server share, so the account is whoever that token belongs to —
     * not a hard-coded username, which the CLI lets the admin change.
     *
     * @return int
     */
    public static function userid(): int {
        global $DB;

        if (self::$userid === null) {
            $token = trim((string) get_config('local_multitopics', 'admin_token'));
            self::$userid = preg_match('/^[a-f0-9]{32}$/i', $token)
                ? (int) $DB->get_field('external_tokens', 'userid', ['token' => $token])
                : 0;
        }
        return self::$userid;
    }

    /**
     * Is this the guest-browsing account?
     *
     * @param int $userid
     * @return bool
     */
    public static function is(int $userid): bool {
        return $userid > 0 && $userid === self::userid();
    }
}
