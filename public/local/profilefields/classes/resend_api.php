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

use core_cache\cache;
use core_text;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * "Send me that confirmation email again", answered without saying who has an account.
 *
 * Backs `local_profilefields_resend_confirmation`, which the app's confirmation
 * screen calls from its Resend button. The rules it enforces are AC-4.2's and
 * already live in {@see verification}; what this class adds is the one thing a
 * pre-login endpoint must get right that the web page next door does not have to:
 * the answer may not reveal whether the address belongs to anybody.
 *
 * The four cases, and the one place they meet
 * -------------------------------------------
 * The caller sees one of: a send (`success: true`), a refusal inside the
 * 60-second cooldown, a refusal at the hourly ceiling, or - for an address with
 * no unconfirmed account behind it - a reply that must be indistinguishable from
 * a send.
 *
 * Indistinguishable is a stronger requirement than it first looks, and it is why
 * this class keeps a **decoy tally**. Answering an unknown address with a plain
 * "sent" is only non-disclosing on the first call: ask twice in ten seconds and a
 * real unconfirmed account starts refusing while an unknown address keeps
 * cheerfully saying "sent", which is a complete account-enumeration oracle - one
 * call to arm it, one to read it. So an address that owns no account is put
 * through the identical rules against a tally of its own, and refuses at exactly
 * the moments a real account would. {@see self::verdict()} is the single
 * implementation both paths run through; neither can drift from the other,
 * because there is nothing to drift from.
 *
 * The decoy tally lives in a cache rather than a table because it is throwaway
 * rate-limit state for addresses we have deliberately decided to know nothing
 * about: it must not accumulate into a list of who has been asked for. The
 * addresses are keyed by a site-salted hash for the same reason, and the whole
 * thing may be purged at any time with no consequence beyond one attacker
 * getting one free probe.
 *
 * What is deliberately *not* equalised is timing: a real send waits on the mail
 * transport and an unknown address does not. Closing that would mean either
 * padding every reply to the slowest possible send - which hands anyone a way to
 * tie up a request thread - or queuing the mail. It is noted here rather than
 * silently ignored.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resend_api {

    /** @var string The cache holding the decoy tallies, defined in db/caches.php. */
    const CACHE = 'resendattempts';

    /** @var string The one machine-readable failure this function has. */
    const ERROR_THROTTLED = 'toomanyrequests';

    /**
     * Send another confirmation link to this address, if that is the right thing to do.
     *
     * @param string $email the address as the learner typed it at sign-up
     * @return array success, message, errorcode, retryafter
     */
    public static function resend(string $email): array {
        // Refused outright when the site does not do email self-registration: there
        // are then no unconfirmed accounts of this kind for anyone to ask about.
        // The same exception the sibling sign-up functions raise, and it says
        // nothing about any particular address.
        signup_api::require_signup_enabled();

        $email = core_text::strtolower(trim($email));
        $user = self::unconfirmed_account($email);

        if ($user === null) {
            return self::decoy($email);
        }

        $verdict = self::verdict(verification::sends($user));
        if ($verdict !== null) {
            return $verdict;
        }

        // AC-4.2.4: rotate first, so the link about to go out is the only live one
        // and every link issued before this moment stops confirming. rotate_secret()
        // also stamps the send, which is what the next call will be judged against.
        verification::rotate_secret($user);

        if (!send_confirmation_email($user)) {
            // Reported to the server's log and to nobody else. A mail-transport
            // failure only ever happens for an address that *has* an account, so
            // saying so out loud would answer the one question this function
            // exists not to answer. The learner taps Resend again; the operator
            // reads the log.
            debugging('local_profilefields: confirmation email failed to send for user ' . $user->id,
                DEBUG_NORMAL);
        }

        // The full cooldown, not what is left of it. Two reasons, and both matter:
        // the clock started when the secret was rotated, a moment before the mail
        // transport was asked to do its work, so counting from now is the honest
        // over-estimate - the button re-enables no earlier than the server will
        // accept. And it is the only way this reply can be identical to the decoy
        // below, which has no mail to send and would otherwise always answer with a
        // rounder number than a real send does.
        return self::sent(verification::cooldown());
    }

    /**
     * Why this caller may not have an email right now, if they may not.
     *
     * The whole of the rate limiting, for both the real and the decoy path. The
     * hourly ceiling is judged before the cooldown: when both apply, "try again in
     * an hour" is the fact that actually governs, and telling somebody to wait
     * forty seconds when the answer will still be no in forty seconds is worse
     * than telling them nothing.
     *
     * @param int[] $sends the send timestamps inside the window, oldest first
     * @return array|null the refusal to return, or null when a send is allowed
     */
    protected static function verdict(array $sends): ?array {
        $hourwait = verification::window_wait_from($sends);
        if ($hourwait > 0) {
            return [
                'success'    => false,
                'message'    => get_string('verifyresendtoomany', 'local_profilefields'),
                'errorcode'  => self::ERROR_THROTTLED,
                'retryafter' => $hourwait,
            ];
        }

        $wait = verification::wait_from($sends);
        if ($wait > 0) {
            return [
                'success'    => false,
                'message'    => get_string('verifyresendtoosoon', 'local_profilefields', $wait),
                'errorcode'  => self::ERROR_THROTTLED,
                'retryafter' => $wait,
            ];
        }

        return null;
    }

    /**
     * The reply to a call that resulted in a link being issued - or that must look
     * exactly as though it had.
     *
     * @param int $retryafter seconds before the button may be tapped again
     * @return array
     */
    protected static function sent(int $retryafter): array {
        return [
            'success'    => true,
            'message'    => null,
            'errorcode'  => null,
            'retryafter' => $retryafter,
        ];
    }

    /**
     * The unconfirmed, self-registered account this address belongs to, if any.
     *
     * Everything that is not one of those is treated as "no account": an address
     * nobody registered, one that is already confirmed, a suspended or deleted
     * account, and an account created by Google or an administrator, which has no
     * confirmation link to reissue. They all get the same reply, which is the
     * point.
     *
     * The comparison is case-insensitive, because sign-up lower-cases the address
     * it stores but accounts predating that rule did not. Where a site allows two
     * accounts to share an address, the newest is the one being confirmed - the
     * older ones are, by definition, not what the learner just signed up with.
     *
     * @param string $email trimmed, lower-cased address
     * @return stdClass|null the user record, or null when there is nothing to send to
     */
    protected static function unconfirmed_account(string $email): ?stdClass {
        global $DB, $CFG;

        if ($email === '' || !validate_email($email)) {
            return null;
        }

        $users = $DB->get_records_select(
            'user',
            $DB->sql_equal('email', ':email', false, true) . '
                 AND mnethostid = :mnethostid
                 AND deleted = 0
                 AND suspended = 0
                 AND confirmed = 0
                 AND auth = :auth',
            [
                'email'      => $email,
                'mnethostid' => $CFG->mnet_localhost_id,
                'auth'       => 'email',
            ],
            'id DESC',
            '*',
            0,
            1
        );

        return $users ? reset($users) : null;
    }

    /**
     * Run an address with no account through the same rules, and say nothing.
     *
     * No email is sent and no account is touched. The only effect is on the tally,
     * so that the second call about this address is refused at the same moment,
     * with the same words and the same `retryafter`, as the second call about an
     * address that does have one.
     *
     * @param string $email trimmed, lower-cased address
     * @return array
     */
    protected static function decoy(string $email): array {
        // An address that is not an address at all cannot be anybody's, and giving
        // it a tally would let a caller fill the cache with garbage keys.
        if ($email === '' || !validate_email($email)) {
            return self::sent(verification::cooldown());
        }

        $cache = cache::make(manager::COMPONENT, self::CACHE);
        $key = self::decoy_key($email);

        $sends = verification::recent((array) $cache->get($key));

        $verdict = self::verdict($sends);
        if ($verdict !== null) {
            return $verdict;
        }

        $sends[] = time();
        $cache->set($key, $sends);

        return self::sent(verification::cooldown());
    }

    /**
     * The cache key for an address, which is not the address.
     *
     * Salted with the site identifier so that the tallies of one site say nothing
     * about another's, and hashed so that a cache dump is not a list of addresses
     * somebody has been probing for.
     *
     * @param string $email trimmed, lower-cased address
     * @return string
     */
    protected static function decoy_key(string $email): string {
        return sha1(get_site_identifier() . '|' . $email);
    }
}
