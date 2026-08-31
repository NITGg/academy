<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_academy.
 */
class observer {

    /**
     * Send a one-time welcome message the first time a user logs in (i.e. right
     * after they confirm their email — Moodle fires no dedicated "user
     * confirmed" event, so first login is the reliable signal).
     *
     * AC-4.1.16 requires this mail "once the email address has been confirmed by
     * either route", and AC-4.3.10 makes an account created through Google or
     * Apple confirmed from the outset. Both therefore qualify, and this used to
     * be gated on `auth === 'email'`, which meant a learner who signed up with
     * Google was welcomed by nobody. The gate is now only about accounts that
     * genuinely have no confirmation step to have passed:
     *
     * - unconfirmed accounts, which have not met the condition yet;
     * - accounts an administrator created by hand or by upload, which never saw
     *   the sign-up screen and would find a "thank you for registering" letter
     *   puzzling.
     *
     * The one-shot user preference below still guarantees "once, ever", so a
     * learner who first appeared before this widened is not welcomed twice.
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        global $DB;

        // A successful sign-in ends any block, so a stale "your account is
        // locked" notice must not outlive it on the next page.
        lockout::clear();

        $user = $DB->get_record('user', ['id' => $event->objectid]);
        if (!$user || !empty($user->deleted) || isguestuser($user)) {
            return;
        }

        // Confirmation is the trigger, so an account that has not confirmed has
        // not earned the letter. (Reaching login at all normally implies it, but
        // an administrator can confirm-and-log-in by other routes.)
        if (empty($user->confirmed)) {
            return;
        }

        // Self-registration only: 'email' is the password sign-up, 'oauth2' is
        // Google. 'manual' is the administrator-created account we skip.
        if (!in_array($user->auth, ['email', 'oauth2'], true)) {
            return;
        }

        // A Google account is confirmed the moment it exists, but AC-4.3.9 says it
        // "is not usable until this screen is completed" - the learner still owes
        // us a country, a phone number and consent. Welcoming them to a
        // registration they have not finished would be premature, and they are
        // about to be redirected to complete.php anyway. That page calls
        // welcome_once() itself once the last box is answered, so nothing is lost
        // by declining here.
        if (class_exists('\local_profilefields\completion')
                && \local_profilefields\completion::enabled()
                && !\local_profilefields\completion::is_complete($user)) {
            return;
        }

        self::welcome_once($user);
    }

    /**
     * Send the registration letter, unless this account has already had it.
     *
     * Public because there are two moments that can be "registration finished",
     * and only one of them is a login. A password account finishes by confirming
     * its address, which logs it in and lands on user_loggedin() above. A Google
     * account finishes by submitting complete.php, which fires no event at all -
     * so that page calls this directly.
     *
     * The user preference is the guard that lets both callers fire freely: the
     * first one through sends, the second finds the flag and returns.
     *
     * @param \stdClass $user the account that has just finished registering
     * @return void
     */
    public static function welcome_once(\stdClass $user): void {
        if (get_user_preferences('local_academy_welcomed', 0, $user)) {
            return;
        }

        // Set before sending, not after: a mail server that times out must not
        // leave the flag clear and have the next page load try again.
        set_user_preference('local_academy_welcomed', 1, $user);
        self::send_welcome($user);
    }

    /** Build and send the welcome notification (in-app + email). */
    private static function send_welcome(\stdClass $user): void {
        // The registration email an admin can edit (Site administration ›
        // Plugins › Local plugins › Purchase & registration emails) replaces
        // this one wherever that plugin is installed; the notification below
        // stays as the fallback so a site without it still welcomes its
        // students.
        if (class_exists('\local_nit_emails\mailer')
                && \local_nit_emails\mailer::handles(\local_nit_emails\templates::EVENT_REGISTRATION)) {
            \local_nit_emails\mailer::send_registration($user);
            return;
        }

        $sitename = format_string(get_site()->fullname);

        $body = get_string('welcome_body', 'local_academy', [
            'name' => fullname($user),
            'site' => $sitename,
        ]);

        $message = new \core\message\message();
        $message->component         = 'local_academy';
        $message->name              = 'welcome';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = get_string('welcome_subject', 'local_academy', $sitename);
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = '<p>' . nl2br(s($body)) . '</p>';
        $message->smallmessage      = get_string('welcome_small', 'local_academy', $sitename);
        $message->notification      = 1;
        $message->contexturl        = (new \moodle_url('/'))->out(false);
        $message->contexturlname    = $sitename;

        message_send($message);
    }

    /**
     * Remember, for the login page, whether this failed attempt left the account
     * locked (AC-4.3.4).
     *
     * The work is in \local_academy\lockout because the app login endpoint needs
     * the same judgement and the same wording; this is only the wiring.
     *
     * @param \core\event\user_login_failed $event
     * @return void
     */
    public static function user_login_failed(\core\event\user_login_failed $event): void {
        lockout::note_failed_attempt($event);
    }

    /**
     * Copy the holder's name the moment a certificate is issued (AC-4.5.1).
     *
     * mod_customcert stores nothing but userid, template and code, and redraws
     * the PDF from the live user record on every download - so without this a
     * profile rename silently rewrites every certificate the person already
     * holds. The work is in \local_academy\certificate_names; this is the wiring.
     *
     * @param \mod_customcert\event\issue_created $event
     * @return void
     */
    public static function certificate_issued(\core\event\base $event): void {
        certificate_names::capture((int) $event->objectid, (int) $event->relateduserid);
    }

    /**
     * Sign the account out of every device whenever its password changes
     * (AC-4.3.10, AC-4.4.7, AC-4.5.2).
     *
     * This one observer covers all four ways a password can change, because all
     * four end at update_internal_user_password(), which is what fires this
     * event: the profile screen on the website, the app's change_password call,
     * the OTP reset, and an administrator resetting it from the user edit form.
     * Two of those live in core files we do not edit, so an event is not merely
     * the tidy way to do this - it is the only way that reaches all four.
     *
     * Doing it here rather than by turning on $CFG->passwordchangetokendeletion
     * also means it is not an administrator setting away from being off again,
     * and it covers the browser half and the token half together.
     *
     * One caveat, which core lives with in the same spot: the event also fires
     * when a password is merely re-hashed with a newer algorithm, which happens
     * on the first successful login of an account whose hash predates Moodle 4.3
     * (and after a pepper rotation). Nothing distinguishes that from a real
     * change at this point, so such an account is signed out once - on the login
     * that upgrades its hash, after which the hash is current and it never
     * happens again. Core's own token deletion in update_internal_user_password()
     * sits inside the same branch and accepts the same one-off.
     *
     * @param \core\event\user_password_updated $event
     * @return void
     */
    public static function user_password_updated(\core\event\user_password_updated $event): void {
        session_terminator::password_changed((int) $event->relateduserid);
    }

    /**
     * Sign a blocked account out of every device the moment it is blocked
     * (AC-4.24.4: "Blocking takes effect immediately: active sessions are
     * terminated").
     *
     * Core already destroys browser sessions on the two screens that suspend an
     * account, but it leaves the web-service tokens alone - so without this a
     * blocked learner is locked out of the website and carries on in the app.
     * (Nothing extra is needed for a *deleted* account: delete_user() clears both
     * stores itself.)
     *
     * The event carries no before/after, so this asks the question the AC is
     * really about - is the account blocked now? - rather than trying to detect
     * the transition. Re-running it on a later edit of an already-blocked account
     * costs one empty delete and changes nothing.
     *
     * @param \core\event\user_updated $event
     * @return void
     */
    public static function user_updated(\core\event\user_updated $event): void {
        global $DB;

        $userid = (int) $event->objectid;
        if ($DB->record_exists('user', ['id' => $userid, 'suspended' => 1, 'deleted' => 0])) {
            session_terminator::blocked($userid);
        }
    }
}
