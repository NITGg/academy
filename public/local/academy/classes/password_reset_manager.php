<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * OTP-based password reset for the mobile app, plus change-password for
 * logged-in users.
 *
 * Flow (forgot password):
 *   1. request_otp(email)      -> emails a 6-digit code, stores its hash.
 *   2. verify_otp(email, otp)  -> checks the code, returns a single-use reset token.
 *   3. reset_password(token, newpassword) -> sets the new password.
 *
 * change_password(userid, current, new) is for an already-logged-in user.
 *
 * Only internal-auth accounts (email/manual) can be reset here — OAuth2/Google
 * users have no local password and should sign in with Google.
 */
class password_reset_manager {

    // The four numbers below are what AC-4.4.4 and AC-4.4.5 are about, so they
    // are administrator-controlled - on the "Password reset" tab of
    // local_profilefields' sign-up and login control panel, which is where an
    // administrator already goes to set the login lock-out and the verification
    // limits. The constants are the shipped defaults and the fallback when a
    // setting has never been saved, so the plugin behaves sanely on a site whose
    // administrator has never opened that page.
    //
    // The screen lives in another plugin, so limits(), limit_value() and
    // set_limit() below are the seam: the setting names and their sane ranges are
    // stated here, once, and nothing outside this class needs to know them.

    /** OTP lifetime, seconds. Overridden by the 'otpttl' setting. */
    const OTP_TTL = 600;            // 10 minutes
    /** Wrong codes allowed before the code is burnt. Overridden by 'otpmaxattempts'. */
    const OTP_MAX_ATTEMPTS = 5;
    /** Rate-limit window for requesting codes. Overridden by 'otprequestwindow'. */
    const REQUEST_WINDOW = 900;     // 15 minutes
    /** Codes requestable per email within that window. Overridden by 'otprequestmax'. */
    const REQUEST_MAX = 5;
    /** Lifetime of the reset token after a successful verify (seconds). */
    const RESET_TTL = 600;          // 10 minutes

    /**
     * Read an administrator-set limit, falling back to the shipped default.
     *
     * A setting that is missing, blank or nonsensical (zero or negative would
     * mean "no codes may ever be requested", or "the code expires before it is
     * sent") falls back rather than bricking password reset for the whole site.
     *
     * @param string $name setting name within the local_academy plugin
     * @param int $default the constant to use when the setting is unusable
     * @return int
     */
    private static function limit(string $name, int $default): int {
        $value = get_config('local_academy', $name);

        if ($value === false || $value === '' || (int) $value <= 0) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * The administrator-settable limits, their defaults and their sane ranges.
     *
     * The single description of these four settings. The screen that edits them
     * is in local_profilefields, and it builds itself from this - so adding a
     * limit, or changing what counts as a sensible value for one, is a change
     * here and nowhere else.
     *
     * 'unit' says how the number is stored versus how a person thinks about it:
     * 'count' is a plain number, 'minutes' is stored in seconds but shown and
     * typed in minutes. 'default', 'min' and 'max' are all in the STORED unit -
     * seconds for a 'minutes' setting - so that set_limit() can clamp without
     * having to know which unit its caller was thinking in.
     *
     * @return array setting name => ['default' => int, 'unit' => string, 'min' => int, 'max' => int]
     */
    public static function limits(): array {
        return [
            'otprequestmax'    => ['default' => self::REQUEST_MAX, 'unit' => 'count',
                                   'min' => 1, 'max' => 50],
            'otprequestwindow' => ['default' => self::REQUEST_WINDOW, 'unit' => 'minutes',
                                   'min' => MINSECS, 'max' => DAYSECS],
            'otpmaxattempts'   => ['default' => self::OTP_MAX_ATTEMPTS, 'unit' => 'count',
                                   'min' => 1, 'max' => 20],
            'otpttl'           => ['default' => self::OTP_TTL, 'unit' => 'minutes',
                                   'min' => MINSECS, 'max' => 2 * HOURSECS],
        ];
    }

    /**
     * The value one limit currently has, in the unit it is stored in.
     *
     * @param string $name one of the keys of limits()
     * @return int
     */
    public static function limit_value(string $name): int {
        $limits = self::limits();

        if (!isset($limits[$name])) {
            throw new \coding_exception("Unknown password-reset limit: {$name}");
        }

        return self::limit($name, $limits[$name]['default']);
    }

    /**
     * Store a new value for one limit, in the unit it is stored in.
     *
     * Clamped to the range declared above rather than trusted: the editing screen
     * is in another plugin and a hand-made POST need not respect the boundaries a
     * number box advertised. Zero requests allowed, or a code that expires before
     * it is sent, would be an outage rather than a setting.
     *
     * @param string $name one of the keys of limits()
     * @param int $value the new value
     * @return void
     */
    public static function set_limit(string $name, int $value): void {
        $limits = self::limits();

        if (!isset($limits[$name])) {
            throw new \coding_exception("Unknown password-reset limit: {$name}");
        }

        $value = min($limits[$name]['max'], max($limits[$name]['min'], $value));

        set_config($name, $value, 'local_academy');
    }

    /** Auth methods whose password we can reset. */
    private static function resettable_auth(string $auth): bool {
        return in_array($auth, ['email', 'manual'], true);
    }

    /**
     * Step 1 — request an OTP. Always returns a generic success (it never reveals
     * whether the email belongs to an account), but only actually emails a code
     * when the account exists and uses a resettable auth method.
     */
    public static function request_otp(string $email): array {
        global $DB, $CFG;

        $email = \core_text::strtolower(trim($email));
        if ($email === '' || !validate_email($email)) {
            throw new \moodle_exception('err_invalidemail', 'local_academy');
        }

        $window = self::limit('otprequestwindow', self::REQUEST_WINDOW);

        // Rows kept only as request history are pruned once they are past both
        // the counting window and the code lifetime, since by then they can
        // neither be counted nor verified. Verified rows still holding a live
        // reset token are spared - their timecreated is old but their token is
        // not, and deleting one would strand somebody mid-reset.
        $DB->delete_records_select('academy_password_otps',
            'timecreated < :cutoff AND expires < :now',
            ['cutoff' => time() - max($window, self::limit('otpttl', self::OTP_TTL)), 'now' => time()]);

        // AC-4.4.4: rate limit per email address. Counted per address rather
        // than per account so that codes requested for an address with no
        // account are throttled identically - otherwise how quickly the
        // endpoint gives up answers "does this address exist?".
        $recent = $DB->count_records_select('academy_password_otps',
            'email = :email AND timecreated > :since',
            ['email' => $email, 'since' => time() - $window]);
        if ($recent >= self::limit('otprequestmax', self::REQUEST_MAX)) {
            throw new \moodle_exception('err_toomanyrequests', 'local_academy');
        }

        $user = $DB->get_record('user', [
            'email' => $email, 'deleted' => 0, 'suspended' => 0,
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);

        if ($user && !isguestuser($user) && self::resettable_auth($user->auth)) {
            // Retire the previous unverified code rather than deleting it. This
            // used to be a delete, which quietly made the rate limit above
            // unreachable: the count is of request rows inside the window, and
            // every request destroyed the rows the next one would have counted,
            // so $recent was never more than 1 and AC-4.4.4 never fired. The row
            // has to outlive the code for the request to be remembered.
            //
            // Retired rows cannot be used: verify_otp() only ever looks at the
            // newest unverified row, and expires = 0 is already in the past.
            $DB->set_field_select('academy_password_otps', 'expires', 0,
                'userid = :uid AND verified = 0', ['uid' => $user->id]);

            $otp = self::generate_code();
            $DB->insert_record('academy_password_otps', (object) [
                'userid'      => $user->id,
                'email'       => $email,
                'otphash'     => password_hash($otp, PASSWORD_DEFAULT),
                'resettoken'  => null,
                'verified'    => 0,
                'attempts'    => 0,
                'expires'     => time() + self::limit('otpttl', self::OTP_TTL),
                'timecreated' => time(),
            ]);
            self::email_otp($user, $otp);
        }

        return ['sent' => true, 'expiresin' => self::limit('otpttl', self::OTP_TTL)];
    }

    /**
     * Step 2 — verify the OTP. On success returns a single-use reset token.
     */
    public static function verify_otp(string $email, string $otp): array {
        global $DB;

        $email = \core_text::strtolower(trim($email));
        $otp = trim($otp);

        // The live code is the newest unverified row that has not been retired.
        // Both conditions matter now that request rows are kept as history for
        // the rate limit: a superseded code is left in the table with expires = 0
        // and must not be picked up here, and two codes requested inside the same
        // second are only ordered apart by id.
        $rows = $DB->get_records_select('academy_password_otps',
            'email = :email AND verified = 0 AND expires > 0', ['email' => $email],
            'timecreated DESC, id DESC', '*', 0, 1);
        $rec = $rows ? reset($rows) : null;

        if (!$rec || $rec->expires < time()) {
            throw new \moodle_exception('err_otpexpired', 'local_academy');
        }
        if ($rec->attempts >= self::limit('otpmaxattempts', self::OTP_MAX_ATTEMPTS)) {
            throw new \moodle_exception('err_otplocked', 'local_academy');
        }
        if (!password_verify($otp, $rec->otphash)) {
            $DB->set_field('academy_password_otps', 'attempts', $rec->attempts + 1, ['id' => $rec->id]);
            throw new \moodle_exception('err_otpinvalid', 'local_academy');
        }

        $resettoken = bin2hex(random_bytes(24));
        $DB->update_record('academy_password_otps', (object) [
            'id'         => $rec->id,
            'verified'   => 1,
            'resettoken' => $resettoken,
            'expires'    => time() + self::RESET_TTL,
        ]);

        return ['resettoken' => $resettoken, 'expiresin' => self::RESET_TTL];
    }

    /**
     * Step 3 — set the new password using the verified reset token.
     */
    public static function reset_password(string $resettoken, string $newpassword): array {
        global $DB;

        $rec = $DB->get_record('academy_password_otps', ['resettoken' => $resettoken, 'verified' => 1]);
        if (!$rec || $rec->expires < time()) {
            throw new \moodle_exception('err_resetexpired', 'local_academy');
        }

        $user = $DB->get_record('user', ['id' => $rec->userid, 'deleted' => 0], '*', MUST_EXIST);

        $errmsg = '';
        if (!check_password_policy($newpassword, $errmsg, $user)) {
            throw new \moodle_exception('err_weakpassword', 'local_academy', '', null, $errmsg);
        }

        update_internal_user_password($user, $newpassword);

        // Invalidate every code/token for this user.
        $DB->delete_records('academy_password_otps', ['userid' => $user->id]);

        return ['reset' => true];
    }

    /**
     * Change password for an already-logged-in user (requires the current one).
     */
    public static function change_password(int $userid, string $current, string $newpassword): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        if (!self::resettable_auth($user->auth)) {
            throw new \moodle_exception('err_authnochange', 'local_academy');
        }
        if (!validate_internal_user_password($user, $current)) {
            throw new \moodle_exception('err_wrongpassword', 'local_academy');
        }

        $errmsg = '';
        if (!check_password_policy($newpassword, $errmsg, $user)) {
            throw new \moodle_exception('err_weakpassword', 'local_academy', '', null, $errmsg);
        }

        update_internal_user_password($user, $newpassword);

        return ['changed' => true];
    }

    // ── helpers ──

    /** A zero-padded 6-digit numeric code. */
    private static function generate_code(): string {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Email the OTP to the user. */
    private static function email_otp(\stdClass $user, string $otp): void {
        $sitename = format_string(get_site()->fullname);
        $subject = get_string('otp_subject', 'local_academy', $sitename);
        $body = get_string('otp_body', 'local_academy', [
            'name' => fullname($user),
            'code' => $otp,
            'mins' => (int) (self::limit('otpttl', self::OTP_TTL) / MINSECS),
            'site' => $sitename,
        ]);
        email_to_user($user, \core_user::get_noreply_user(), $subject, $body,
            '<p>' . nl2br(s($body)) . '</p>');
    }
}
