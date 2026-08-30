<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Says out loud what core only does silently: this account is locked.
 *
 * Moodle already blocks an account after $CFG->lockoutthreshold consecutive
 * failed sign-ins (SRS AC-4.3.2 - admin-controlled under Site administration >
 * Security > Site security settings, together with the window it counts over
 * and how long the block lasts). None of that is reimplemented here.
 *
 * What core does not do is *tell the learner*, which is what AC-4.3.4 asks for:
 *
 * - On the web, login_is_lockedout() is consulted at the START of
 *   authenticate_user_login(). The attempt that actually trips the lock is
 *   therefore reported as an ordinary bad password, and the real message only
 *   surfaces on the attempt after it - a tester who stops at five sees nothing.
 * - On /login/token.php, which is where the app signs in, the failure reason is
 *   discarded outright: every failure becomes 'invalidlogin', so a blocked
 *   learner is told their password is wrong and keeps trying for 15 minutes.
 *
 * Both channels read their wording from here, so the web page and the app can
 * never drift into saying different things about the same account state.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lockout {

    /**
     * Session key carrying "the attempt just made in this session left the
     * account locked", set by the observer and consumed by the login page.
     *
     * It exists because of the ordering described above: on the fifth attempt
     * the lock is applied *after* login/index.php has already settled on the
     * generic message, so the page needs a way to learn what the failed attempt
     * did. The value is the finished, translated sentence - a renderer should
     * never be deciding wording.
     */
    const SESSION_FLAG = 'local_academy_lockoutnotice';

    /**
     * Is this account locked out right now?
     *
     * Thin wrapper over core so callers do not have to remember to require
     * authlib, and so "locked" means exactly what core means by it (including
     * the lockout-ignored preference and an expired lock auto-clearing).
     *
     * @param \stdClass $user full user record
     * @return bool
     */
    public static function is_locked(\stdClass $user): bool {
        global $CFG;

        // Required lazily rather than at file scope: this is an autoloaded class,
        // and autoloading one should not pull half of core in as a side effect.
        // authenticate_user_login() has normally loaded it already anyway.
        require_once($CFG->libdir . "/authlib.php");

        return (bool) login_is_lockedout($user);
    }

    /**
     * Seconds left on the block, or 0 when the block does not expire by itself.
     *
     * $CFG->lockoutduration empty means "locked until an admin or the unlock
     * email releases it" - core treats that as locked forever, and so do we.
     *
     * @param \stdClass $user full user record
     * @return int seconds remaining, 0 if the lock has no expiry
     */
    public static function remaining_seconds(\stdClass $user): int {
        global $CFG;

        if (empty($CFG->lockoutduration)) {
            return 0;
        }

        $lockedat = (int) get_user_preferences('login_lockout', 0, $user);
        if ($lockedat <= 0) {
            return 0;
        }

        return (int) max(0, ($lockedat + (int) $CFG->lockoutduration) - time());
    }

    /**
     * Pick the string and its placeholders for this account's block.
     *
     * The one place the two channels agree on. It returns the pair rather than
     * finished text so message() and exception() can each package it the way
     * their caller needs, without either of them re-deciding the wording.
     *
     * The wait is rounded up to whole minutes before format_time() sees it:
     * "14 mins 32 secs" is needlessly precise for someone already frustrated,
     * and rounding upward keeps the advice from being optimistic.
     *
     * @param \stdClass $user full user record
     * @return array [string key, mixed $a for get_string()]
     */
    private static function wording(\stdClass $user): array {
        global $CFG;

        $attempts = (int) ($CFG->lockoutthreshold ?? 0);
        $remaining = self::remaining_seconds($user);

        if ($remaining <= 0) {
            return ['lockout_blocked_nowait', $attempts];
        }

        return ['lockout_blocked', (object) [
            'attempts' => $attempts,
            'wait'     => format_time((int) (ceil($remaining / MINSECS) * MINSECS)),
        ]];
    }

    /**
     * The sentence to show a learner whose account is locked.
     *
     * @param \stdClass $user full user record
     * @return string translated, ready to display
     */
    public static function message(\stdClass $user): string {
        [$key, $a] = self::wording($user);

        return get_string($key, 'local_academy', $a);
    }

    /**
     * The same judgement, as an exception the JSON API can let bubble up.
     *
     * Exists so the app endpoint never re-decides the wording: both channels
     * come through wording() and therefore always describe the same account
     * state the same way.
     *
     * @param \stdClass $user full user record
     * @return \moodle_exception
     */
    public static function exception(\stdClass $user): \moodle_exception {
        [$key, $a] = self::wording($user);

        return new \moodle_exception($key, 'local_academy', '', $a);
    }

    /**
     * Observer for \core\event\user_login_failed: remember whether this attempt
     * left the account locked.
     *
     * Safe to run on the attempt that trips the lock because core calls
     * login_attempt_failed() - which applies the lock - *before* it triggers the
     * event, so by the time we look the preference is already written.
     *
     * A failure that did not lock the account clears any stale flag, so a
     * learner who waits out the block and mistypes their password once is not
     * shown a lockout notice that no longer applies.
     *
     * @param \core\event\user_login_failed $event
     * @return void
     */
    public static function note_failed_attempt(\core\event\user_login_failed $event): void {
        global $SESSION;

        $userid = (int) $event->userid;
        if ($userid <= 0) {
            // No such account. Core never locks those, and saying anything at
            // all here would confirm which usernames exist.
            return;
        }

        $user = \core_user::get_user($userid);
        if (!$user || !self::is_locked($user)) {
            self::clear();
            return;
        }

        $SESSION->{self::SESSION_FLAG} = self::message($user);
    }

    /**
     * Read and discard the pending notice.
     *
     * One-shot on purpose: the notice describes one attempt, so it must not
     * survive to a later page load and reappear next to an unrelated error.
     *
     * @return string|null the message, or null when the last attempt was an
     *                     ordinary failure
     */
    public static function take_pending_notice(): ?string {
        global $SESSION;

        $message = $SESSION->{self::SESSION_FLAG} ?? null;
        self::clear();

        return ($message === null || $message === '') ? null : (string) $message;
    }

    /**
     * Drop the pending notice without reading it.
     *
     * @return void
     */
    public static function clear(): void {
        global $SESSION;

        unset($SESSION->{self::SESSION_FLAG});
    }
}
