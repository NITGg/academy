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
 * cli/app_guest_token.php builds one read-only token so the catalogue can be
 * browsed before sign-in. To Moodle that token is a signed-in user like any
 * other — but the person holding the phone is a visitor: there is no profile to
 * read a country from, and nobody to send to "set your country". Anything that
 * treats visitors differently from members — pricing, above all — asks here
 * before trusting $USER->id.
 *
 * The account is recognised three ways, any one of which is enough: the
 * `nit_app_guest` system role the CLI assigns it (the role exists for exactly
 * this: to mark the account); the "Guest browsing account" id typed into the
 * Mobile app settings page (for an account made by hand); and owning the token
 * getsettings.php publishes.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class app_guest {

    /** @var string Shortname of the system role cli/app_guest_token.php gives the account. */
    const ROLE = 'nit_app_guest';

    /** @var array<int, string>|null userid => how it was recognised; null until looked up. */
    private static $accounts = null;

    /**
     * Every guest-browsing account, keyed by id, memoised for the request.
     *
     * Normally exactly one. Empty when the CLI has never been run on this site.
     *
     * @return array<int, string> userid => 'role' | 'setting' | 'token'
     */
    public static function accounts(): array {
        global $DB;

        if (self::$accounts !== null) {
            return self::$accounts;
        }
        self::$accounts = [];

        // 1. Holders of the marker role, in the system context.
        $holders = $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE r.shortname = :role AND ra.contextid = :ctx",
            ['role' => self::ROLE, 'ctx' => \context_system::instance()->id]);
        foreach ($holders as $userid) {
            self::$accounts[(int) $userid] = 'role';
        }

        // 2. The account named on the settings page.
        $named = (int) get_config('local_multitopics', 'guest_userid');
        if ($named > 0 && !isset(self::$accounts[$named])) {
            self::$accounts[$named] = 'setting';
        }

        // 3. The owner of the published token.
        $token = trim((string) get_config('local_multitopics', 'admin_token'));
        if (preg_match('/^[a-f0-9]{32}$/i', $token)) {
            $owner = (int) $DB->get_field('external_tokens', 'userid', ['token' => $token]);
            if ($owner > 0 && !isset(self::$accounts[$owner])) {
                self::$accounts[$owner] = 'token';
            }
        }

        return self::$accounts;
    }

    /**
     * Is this the guest-browsing account?
     *
     * @param int $userid
     * @return bool
     */
    public static function is(int $userid): bool {
        return $userid > 0 && isset(self::accounts()[$userid]);
    }

    /**
     * The guest-browsing account's id, or 0 when there is none.
     *
     * @return int
     */
    public static function userid(): int {
        $accounts = self::accounts();
        return $accounts ? (int) array_key_first($accounts) : 0;
    }
}
