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
 * The lifetime and resend rules AC-4.2 puts around the confirmation link.
 *
 * Moodle's own confirmation is deliberately permissive: `auth_email` stores one
 * random secret on the user record, `/login/confirm.php` compares it, and that
 * is the whole mechanism. It has three properties the specification does not
 * accept:
 *
 * - the secret never expires (AC-4.2.10 wants 24 hours);
 * - a resend re-sends the *same* secret, so no earlier link is invalidated
 *   (AC-4.2.4 wants every previous link dead);
 * - resends are unlimited (AC-4.2.2 wants a 60-second wait, AC-4.2.3 a ceiling
 *   of five an hour).
 *
 * Rather than replace core's flow - which would mean owning a confirmation page,
 * an email template and a URL forever - this class layers the three missing rules
 * on top of it, keeping core's secret as the credential. Two user preferences
 * carry the state:
 *
 * - `local_profilefields_issued`: when the current secret was minted;
 * - `local_profilefields_sends`: the timestamps of recent sends, newest last.
 *
 * Preferences rather than a table because the data is per-user, tiny, and dies
 * with the account; a table would need its own cleanup task to say the same
 * thing.
 *
 * The enforcement points are in {@see hook_callbacks}, which watches for the two
 * core scripts involved before they run.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class verification {

    /** @var string Preference holding the issue time of the live secret. */
    const PREF_ISSUED = 'local_profilefields_issued';

    /** @var string Preference holding recent send timestamps, comma separated. */
    const PREF_SENDS = 'local_profilefields_sends';

    /** @var int The window the send ceiling is measured over (AC-4.2.3). */
    const WINDOW = HOURSECS;

    /**
     * How long a confirmation link stays usable, in seconds.
     *
     * @return int
     */
    public static function link_ttl(): int {
        return ((int) (get_config(manager::COMPONENT, 'linkttlhours') ?: 24)) * HOURSECS;
    }

    /**
     * The enforced wait between two sends, in seconds (AC-4.2.2).
     *
     * @return int
     */
    public static function cooldown(): int {
        $value = get_config(manager::COMPONENT, 'resendcooldown');

        return $value === false ? 60 : (int) $value;
    }

    /**
     * How many sends an account may ask for within the hour (AC-4.2.3).
     *
     * @return int
     */
    public static function max_sends(): int {
        return (int) (get_config(manager::COMPONENT, 'resendmax') ?: 5);
    }

    /**
     * Record that a confirmation link has just been issued to this account.
     *
     * Called from the `user_created` observer for the first mail, and from
     * {@see prepare_resend()} for every one after it. Both the issue time and the
     * send tally are stamped here so the two can never disagree about whether a
     * mail went out.
     *
     * @param stdClass $user the account the link was sent to
     * @return void
     */
    public static function stamp_issued(stdClass $user): void {
        $now = time();

        set_user_preference(self::PREF_ISSUED, $now, $user->id);
        set_user_preference(self::PREF_SENDS, implode(',', array_merge(self::sends($user), [$now])), $user->id);
    }

    /**
     * The send timestamps still inside the rate-limit window, oldest first.
     *
     * Anything older than the window is dropped on read rather than by a cleanup
     * task: the list is only ever consulted here, so an entry nobody will look at
     * again costs nothing until the next read tidies it away.
     *
     * @param stdClass $user
     * @return int[]
     */
    protected static function sends(stdClass $user): array {
        $raw = (string) get_user_preferences(self::PREF_SENDS, '', $user->id);
        if ($raw === '') {
            return [];
        }

        $cutoff = time() - self::WINDOW;
        $recent = array_filter(
            array_map('intval', explode(',', $raw)),
            static fn(int $stamp): bool => $stamp > $cutoff
        );

        return array_values($recent);
    }

    /**
     * Seconds still to wait before another confirmation email may be requested.
     *
     * Drives the live countdown on the notice screen as well as the refusal, so
     * that what the button says and what the server does cannot drift apart.
     *
     * @param stdClass $user
     * @return int zero when a send is allowed right now
     */
    public static function seconds_until_resend(stdClass $user): int {
        $sends = self::sends($user);
        if (!$sends) {
            return 0;
        }

        $elapsed = time() - (int) end($sends);

        return max(0, self::cooldown() - $elapsed);
    }

    /**
     * Has this account used up its sends for the hour? (AC-4.2.3)
     *
     * @param stdClass $user
     * @return bool
     */
    public static function send_limit_reached(stdClass $user): bool {
        return count(self::sends($user)) >= self::max_sends();
    }

    /**
     * Has the live confirmation link passed its expiry? (AC-4.2.10)
     *
     * An account with no issue time recorded is treated as valid. Those are the
     * accounts created before this class existed, and expiring their links
     * retroactively would lock out people holding a mail that was legitimate when
     * it was sent.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function link_expired(stdClass $user): bool {
        $issued = (int) get_user_preferences(self::PREF_ISSUED, 0, $user->id);

        if ($issued <= 0) {
            return false;
        }

        return (time() - $issued) > self::link_ttl();
    }

    /**
     * Mint a fresh secret and stamp it, so the mail core is about to send is new.
     *
     * This is what delivers AC-4.2.4. Core's `send_confirmation_email()` re-sends
     * whatever secret is already on the user record, so without replacing it here
     * a learner who asks three times ends up holding three working links - and the
     * oldest of them outlives the expiry of the newest.
     *
     * Writes the secret straight to the user record rather than through
     * `user_update_user()`: this runs before the page that will read `$user` has
     * fetched it, and the heavier call would fire a user_updated event for what is
     * an internal credential rotation.
     *
     * @param stdClass $user the account about to be sent a new link
     * @return string the new secret, also written to the user record
     */
    public static function rotate_secret(stdClass $user): string {
        global $DB;

        $secret = random_string(15);

        $DB->set_field('user', 'secret', $secret, ['id' => $user->id]);
        $user->secret = $secret;

        self::stamp_issued($user);

        return $secret;
    }

    /**
     * May this account be sent another confirmation link, and if not, why not?
     *
     * @param stdClass $user
     * @return string|null the localised refusal, or null when a send is allowed
     */
    public static function refuse_resend(stdClass $user): ?string {
        if (self::send_limit_reached($user)) {
            return get_string('verifyresendtoomany', 'local_profilefields');
        }

        $wait = self::seconds_until_resend($user);
        if ($wait > 0) {
            // Not 'verifyresendwait' - that one is the button's own countdown
            // label ("Resend email (42s)"), which reads as a stray fragment when
            // it turns up as a refusal notice. This path is only reached without
            // JavaScript, or by a request that skipped the button entirely.
            return get_string('verifyresendtoosoon', 'local_profilefields', $wait);
        }

        return null;
    }

    /**
     * Clear every trace of the verification state for an account.
     *
     * Called once the address is confirmed: the counters have done their job, and
     * leaving them behind would rate-limit a later, unrelated send.
     *
     * @param stdClass $user
     * @return void
     */
    public static function clear(stdClass $user): void {
        unset_user_preference(self::PREF_ISSUED, $user->id);
        unset_user_preference(self::PREF_SENDS, $user->id);
    }

    /**
     * The unconfirmed account a confirmation URL refers to, if it names one.
     *
     * `/login/confirm.php` carries `data=secret/username`, older mail carries the
     * secret in `p` with the username in `s`. Both shapes are read so that a link
     * sent before this code existed is still understood.
     *
     * @param string $data the `data` parameter, possibly empty
     * @param string $username the `s` parameter, possibly empty
     * @return stdClass|null the user record, or null when nothing matches
     */
    public static function user_from_link(string $data, string $username): ?stdClass {
        global $DB, $CFG;

        if ($data !== '') {
            $parts = explode('/', $data, 2);
            $username = $parts[1] ?? '';
        }

        $username = trim($username);
        if ($username === '') {
            return null;
        }

        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ]);

        return $user ?: null;
    }
}
