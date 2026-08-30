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
}
