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
 * Event observers for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Start the clock on a new account's confirmation link.
     *
     * The link is sent by `auth_email::user_signup()`, which fires no event of its
     * own, so `user_created` is the nearest signal - it is raised by
     * `user_create_user()` immediately before the mail goes out. Stamping here
     * rather than in the sending code keeps the rule in one place: every route
     * that creates a self-registered account (the web form, the app's web
     * service) goes through `user_create_user()`.
     *
     * Only unconfirmed self-registrations are stamped. An account an administrator
     * created is confirmed on arrival and has no link to expire, and stamping it
     * would put an expiry on a confirmation that never happens.
     *
     * @param \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event): void {
        global $DB;

        try {
            $user = $DB->get_record('user', ['id' => $event->objectid], 'id, confirmed, auth, deleted');

            if (!$user || !empty($user->deleted) || !empty($user->confirmed)) {
                return;
            }
            if ($user->auth !== 'email') {
                return;
            }

            verification::stamp_issued($user);

            // The learner ticked the Terms box on the form, so record it against
            // the policy documents it named. Without this tool_policy has no
            // record, and asks them to agree a second time the moment they open
            // the confirmation link. The value is read from the request because
            // that is where the tick still is - the account was created from it a
            // moment ago and it is not stored on the user record.
            if (manager::consent_enabled()
                    && optional_param(signup::CONSENT, 0, PARAM_BOOL)) {
                policies::record_acceptance((int) $user->id);
            }

            // Remember who just registered, so the notice core is about to print
            // can be replaced by ours. This event is raised inside
            // auth_email::user_signup(), a few lines before it renders that
            // notice, and it is the only place the new id is available to us -
            // nobody is logged in, and the page holds no other handle on the
            // account. Read and cleared in hook_callbacks::before_http_headers().
            global $SESSION;
            $SESSION->local_profilefields_justsignedup = (int) $user->id;
        } catch (\Throwable $e) {
            debugging('local_profilefields user_created: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Retire the verification counters once the address has been proved.
     *
     * Moodle raises no "user confirmed" event, so first login stands in for it -
     * confirmation logs the account in, and an account that has logged in has
     * necessarily been confirmed. Leaving the counters behind would mean a learner
     * who used four of their five sends during sign-up carried that tally into
     * some unrelated send months later.
     *
     * @param \core\event\user_loggedin $event
     * @return void
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        try {
            $user = $DB->get_record('user', ['id' => $event->objectid], 'id, confirmed');

            if ($user && !empty($user->confirmed)) {
                verification::clear($user);
            }

            // AC-4.3.5. Only a login that actually carried the ticked box earns a
            // token: a session restored *from* a token does not post the field, so
            // rememberme::resume() stays the only thing that rotates one, and this
            // cannot double-issue behind it.
            if ($user && optional_param('nitrememberme', 0, PARAM_BOOL)) {
                $full = $DB->get_record('user', ['id' => $user->id, 'deleted' => 0]);
                if ($full) {
                    rememberme::remember($full);
                }
            }
        } catch (\Throwable $e) {
            debugging('local_profilefields user_loggedin: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Withdraw this device's trust when the learner signs out of it.
     *
     * "Keep me signed in" is a statement about one device, so signing out of that
     * device retracts it. Other devices the learner has trusted are untouched -
     * logging out of a library computer should not sign you out of your phone.
     *
     * @param \core\event\user_loggedout $event
     * @return void
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        try {
            rememberme::forget();
        } catch (\Throwable $e) {
            debugging('local_profilefields user_loggedout: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Destroy every remembered device when the account's password changes.
     *
     * AC-4.3.11 and AC-4.4.7 both require that changing a password ends existing
     * sessions. A "Remember me" token is a credential that can *create* a session,
     * so ending the sessions while leaving the tokens alive would defeat the point
     * entirely: whoever prompted the password change would simply be signed back
     * in on their next page load.
     *
     * @param \core\event\user_password_updated $event
     * @return void
     */
    public static function user_password_updated(\core\event\user_password_updated $event): void {
        global $DB;

        try {
            $userid = (int) $event->relateduserid;

            rememberme::revoke_all($userid);

            // AC-4.4.7 also asks for a confirmation email. Core terminates the
            // sessions (given $CFG->passwordchangelogout, set on upgrade) but tells
            // nobody, and this letter is the only thing that reaches an owner whose
            // password was changed by somebody else - the sessions being killed is
            // invisible to them, since they were not the one signed in.
            //
            // Sent directly rather than through the message API: AC-4.5.5 puts
            // security mail beyond the reach of the preference screen.
            $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
            if (!$user || isguestuser($user) || empty($user->email)) {
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
                get_string('resetdonesubject', 'local_profilefields'),
                get_string('resetdonebody', 'local_profilefields', $data)
            );
        } catch (\Throwable $e) {
            debugging('local_profilefields user_password_updated: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
