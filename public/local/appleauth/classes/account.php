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
 * The Moodle account behind an Apple ID - shared by the app endpoint (token.php)
 * and the web sign-in (web_callback.php), so both find, create and gate accounts
 * the same way.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account {

    /** Domain of the never-deliverable address given to an account Apple hid the e-mail of. */
    const PLACEHOLDER_DOMAIN = '@privaterelay.invalid';

    /**
     * Never-deliverable address for an Apple account that hid its e-mail
     * (`.invalid` is reserved, RFC 2606).
     *
     * @param string $sub Apple subject
     * @return string
     */
    public static function placeholder_email(string $sub): string {
        return 'apple.' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $sub), 0, 120) . self::PLACEHOLDER_DOMAIN;
    }

    /**
     * Find the Moodle account for an Apple profile, creating it when allowed.
     *
     * By Apple subject first — Apple may stop sending the e-mail after the first
     * login — then by verified e-mail (links an existing account), else created.
     *
     * @param \stdClass $profile from apple_token::verify()
     * @param string $firstname name Apple gave on the first authorisation ('' if none)
     * @param string $lastname
     * @param \stdClass $config plugin config
     * @return array [user record, bool is_new]
     * @throws auth_error
     */
    public static function resolve(\stdClass $profile, string $firstname, string $lastname, \stdClass $config): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/lib.php');

        $where = ['deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id];
        $link = $DB->get_record('local_appleauth_links', ['sub' => $profile->sub]);
        $user = $link ? $DB->get_record('user', ['id' => $link->userid] + $where) : false;

        if (!$user && $profile->email && $profile->email_verified) {
            if ($DB->count_records('user', ['email' => $profile->email] + $where) > 1) {
                throw new auth_error('email_not_unique', 409);
            }
            $user = $DB->get_record('user', ['email' => $profile->email] + $where);
        }

        $isnew = false;
        if (!$user) {
            if (empty($config->allowcreate)) {
                throw new auth_error('user_not_found', 404);
            }
            // An address another account already uses must not be taken over.
            $email = $profile->email;
            if (!$email || $DB->record_exists('user', ['email' => $email] + $where)) {
                $email = self::placeholder_email($profile->sub);
            }

            $localpart = ($profile->email && !$profile->is_private_email) ? strstr($profile->email, '@', true) : '';
            $base = \core_user::clean_field($localpart, 'username');
            if (empty($base)) {
                $base = 'apple' . \core_text::strtolower(substr(preg_replace('/[^a-zA-Z0-9]/', '', $profile->sub), 0, 12));
            }
            $username = $base;
            for ($i = 1; $DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]); $i++) {
                $username = $base . $i;
            }

            $newuser = (object) [
                'auth' => !empty($config->newuserauth) ? $config->newuserauth : 'oauth2',
                'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id,
                'username' => $username,
                'email' => $email,
                'firstname' => $firstname !== '' ? $firstname : 'Apple',
                'lastname' => $lastname !== '' ? $lastname : 'User',
                'lang' => current_language(),
            ];
            $user = $DB->get_record('user', ['id' => user_create_user($newuser, false, true)], '*', MUST_EXIST);
            $isnew = true;
        } else if ($profile->email && $profile->email_verified
                && substr($user->email, -strlen(self::PLACEHOLDER_DOMAIN)) === self::PLACEHOLDER_DOMAIN
                && !$DB->record_exists('user', ['email' => $profile->email] + $where)) {
            // Replace the placeholder once Apple does share a verified address.
            $DB->set_field('user', 'email', $profile->email, ['id' => $user->id]);
            $user->email = $profile->email;
        }

        // SSO link policy (same as local_googleauth): only accounts whose auth method
        // is allowed may be signed into via Apple, so a Sign in with Apple cannot take
        // over a local password account that merely shares the e-mail.
        $allowedlinkauth = array_filter(array_map('trim', explode(',', (string) ($config->allowedlinkauth ?? 'oauth2'))));
        if (!in_array($user->auth, $allowedlinkauth ?: ['oauth2'], true)) {
            throw new auth_error('auth_not_linkable', 403);
        }

        $now = time();
        if ($link) {
            $DB->update_record('local_appleauth_links', (object) [
                'id' => $link->id, 'userid' => $user->id, 'email' => $profile->email ?? $link->email, 'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_appleauth_links', (object) [
                'userid' => $user->id, 'sub' => $profile->sub, 'email' => $profile->email,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        return [$user, $isnew];
    }

    /**
     * Standard account gates (mirror /login/token.php).
     *
     * @param \stdClass $user
     * @throws auth_error
     */
    public static function require_can_login(\stdClass $user): void {
        global $CFG;

        if (isguestuser($user)) {
            throw new auth_error('guest_not_allowed', 403);
        }
        if (!empty($user->suspended)) {
            throw new auth_error('user_suspended', 403);
        }
        if (empty($user->confirmed)) {
            throw new auth_error('user_not_confirmed', 403);
        }
        if (!empty($CFG->maintenance_enabled)
                && !has_capability('moodle/site:maintenanceaccess', \context_system::instance(), $user)) {
            throw new auth_error('site_maintenance', 503);
        }
    }
}
