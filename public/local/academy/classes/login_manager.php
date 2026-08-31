<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Sign the app in, and say why when it fails.
 *
 * This is a deliberate mirror of /login/token.php, which is core and therefore
 * out of bounds for editing. The reason it exists is that file's last four
 * lines: core discards the failure reason and reports every unsuccessful
 * sign-in as 'invalidlogin', so a learner whose account has been blocked after
 * five failed attempts (AC-4.3.2) is told their password is wrong and keeps
 * retrying for the whole lockout. AC-4.3.4 wants them told what really happened.
 *
 * IMPORTANT - keep in step with /login/token.php. Every check below exists there
 * too, in the same order; nothing has been dropped, relaxed or reordered, and
 * each one calls the same core function core calls rather than reimplementing
 * it. Re-read token.php against this file on every Moodle upgrade: if core grows
 * a new guard, this file does not inherit it. That review is noted in CLAUDE.md.
 *
 * token.php itself is untouched and still works, so older builds of the app and
 * the stock Moodle app keep signing in exactly as they do today.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class login_manager {

    /**
     * Authenticate and return a web-service token.
     *
     * @param string $username username, or email when $CFG->authloginviaemail is on
     * @param string $password the password as typed
     * @param string $service shortname of the external service to issue a token for
     * @return array token payload for the JSON envelope
     * @throws \moodle_exception on any failure, carrying a message meant for the user
     */
    public static function login(string $username, string $password, string $service): array {
        global $CFG, $DB, $USER;

        if (empty($CFG->enablewebservices)) {
            throw self::fail('enablewsdescription', 'enablewsdescription', 'webservice');
        }

        $username = trim(\core_text::strtolower($username));

        if (is_restored_user($username)) {
            throw self::fail('restoredaccountresetpassword', 'restoredaccountresetpassword', 'webservice');
        }

        // Third argument false = do NOT ignore the lockout, so this endpoint is
        // exactly as brute-force-resistant as token.php: this same call is what
        // counts the failure and applies the block.
        $failurereason = null;
        $user = authenticate_user_login($username, $password, false, $failurereason, false);

        if (empty($user)) {
            throw self::failure_exception($username);
        }

        $systemcontext = \context_system::instance();

        if (!empty($CFG->maintenance_enabled)
                && !has_capability('moodle/site:maintenanceaccess', $systemcontext, $user)) {
            throw self::fail('sitemaintenance', 'sitemaintenance', 'admin');
        }

        if (isguestuser($user)) {
            throw self::fail('noguest', 'noguest', 'error');
        }

        if (empty($user->confirmed)) {
            // Our own wording, not core's. Core's 'usernotconfirmed' reads
            // "Could not confirm {$a}", which belongs to a failed confirmation
            // link rather than to a sign-in, and it lives in lang/en/moodle.php
            // where moodle_exception cannot reach it (see fail() below). The
            // errorcode stays 'usernotconfirmed' so clients keep branching on the
            // same value they already do.
            throw self::fail('usernotconfirmed', 'err_usernotconfirmed', 'local_academy');
        }

        $userauth = get_auth_plugin($user->auth);
        if (!empty($userauth->config->expiration) && $userauth->config->expiration == 1) {
            $days2expire = $userauth->password_expire($user->username);
            if ((int) $days2expire < 0) {
                throw self::fail('passwordisexpired', 'passwordisexpired', 'webservice');
            }
        }

        enrol_check_plugins($user);

        // The dispatcher set $USER to the shared registration account to get this
        // far; from here the request belongs to the learner, and
        // generate_token_for_current_user() reads $USER.
        \core\session\manager::set_user($user);

        $servicerecord = $DB->get_record('external_services', ['shortname' => $service, 'enabled' => 1]);
        if (empty($servicerecord)) {
            throw self::fail('servicenotavailable', 'servicenotavailable', 'webservice');
        }

        // Core mints the token: same table, same reuse and expiry rules, so the
        // ws_diagnose CLI and every other token tool keep working unchanged.
        $token = \core_external\util::generate_token_for_current_user($servicerecord);
        $privatetoken = $token->privatetoken;
        \core_external\util::log_token_request($token);

        $siteadmin = has_capability('moodle/site:config', $systemcontext, $USER->id);

        return [
            'token'        => $token->token,
            // Same rule as token.php: the private token is for https only, and
            // never for an administrator.
            'privatetoken' => (is_https() && !$siteadmin) ? $privatetoken : null,
            'userid'       => (int) $user->id,
        ];
    }

    /**
     * Decide what to tell the client about a failed sign-in.
     *
     * The whole point of the class. A blocked account gets the lockout sentence
     * (how long is left, and that an unlock link is in their inbox); everything
     * else gets core's generic wording, so a wrong password on a real account
     * still reads the same as any password on an account that does not exist.
     *
     * The account is re-read rather than trusting $failurereason:
     * AUTH_LOGIN_LOCKOUT is only returned when the account was ALREADY locked
     * when the call began. On the attempt that trips the lock the reason is a
     * plain failure - and that attempt is precisely the one AC-4.3.4 is about.
     *
     * @param string $username the username as authenticate_user_login() received it
     * @return \moodle_exception
     */
    private static function failure_exception(string $username): \moodle_exception {
        $user = self::locate_account($username);

        if ($user && lockout::is_locked($user)) {
            return lockout::exception($user);
        }

        // Our own copy of core's wording rather than core's 'invalidlogin' - see
        // fail() for why core's cannot be reached from here. Keep
        // err_invalidlogin worded identically to core's invalidlogin.
        return self::fail('invalidlogin', 'err_invalidlogin', 'local_academy');
    }

    /**
     * Build a failure the JSON envelope can describe completely.
     *
     * Two things are separated on purpose:
     *
     * - the **message**, which is for a person to read and is therefore
     *   translated and free to be reworded at any time;
     * - the **errorcode**, which is for the app to branch on and must therefore
     *   never change once clients depend on it.
     *
     * Clients used to have to parse the message to tell one failure from
     * another, which meant any rewording silently broke them. The codes below are
     * the ones /login/token.php puts in its own `errorcode` field, so a client can
     * use one table for both endpoints.
     *
     * The indirection also works around a trap that has bitten this file twice:
     * moodle_exception rewrites the components 'moodle' and 'core' to 'error' in
     * its constructor, so any core string that lives in lang/en/moodle.php -
     * 'invalidlogin' and 'usernotconfirmed' among them - can never be resolved
     * through it, and getMessage() returns the literal "error/invalidlogin"
     * instead. Core gets away with it because its error handler renders the
     * exception by hand; a JSON dispatcher reading getMessage() does not. Those
     * two cases therefore carry our own translated string with the core errorcode
     * pinned back on.
     *
     * @param string $apicode stable machine-readable code for the client
     * @param string $identifier lang string identifier for the human message
     * @param string $component component the identifier lives in
     * @param mixed $a placeholder data for the string
     * @return \moodle_exception
     */
    private static function fail(string $apicode, string $identifier, string $component, $a = null): \moodle_exception {
        $exception = new \moodle_exception($identifier, $component, '', $a);

        // Set after construction: moodle_exception uses errorcode to find the
        // string, so it cannot be the client-facing code until the lookup is done.
        $exception->errorcode = $apicode;

        return $exception;
    }

    /**
     * Find the account the failed attempt was aimed at.
     *
     * Mirrors the lookup at the top of authenticate_user_login(): username
     * first, then - only when $CFG->authloginviaemail is on - a unique email
     * match. Resolving it the same way matters, because the site lets people
     * sign in with either and we must inspect the same record core just locked.
     *
     * @param string $username username or email, already lowercased
     * @return \stdClass|null full user record, or null when there is no such account
     */
    private static function locate_account(string $username): ?\stdClass {
        global $CFG, $DB;

        if ($user = get_complete_user_data('username', $username, $CFG->mnet_localhost_id)) {
            return $user;
        }

        if (empty($CFG->authloginviaemail)) {
            return null;
        }

        $email = clean_param($username, PARAM_EMAIL);
        if (!$email) {
            return null;
        }

        // Two rows means the address is ambiguous. Core refuses to log in on an
        // ambiguous email, so there is no single account for us to report on.
        $users = $DB->get_records_select(
            'user',
            'mnethostid = :mnethostid AND LOWER(email) = LOWER(:email) AND deleted = 0',
            ['mnethostid' => $CFG->mnet_localhost_id, 'email' => $email],
            'id',
            'id',
            0,
            2
        );

        if (count($users) !== 1) {
            return null;
        }

        $found = reset($users);

        return get_complete_user_data('id', $found->id) ?: null;
    }
}
