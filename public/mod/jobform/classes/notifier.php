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

namespace mod_jobform;

/**
 * The two messages that leave the site when an application is sent (AC-4.20.8):
 * an acknowledgement email to the applicant, and a notification to whoever
 * reviews the submissions.
 *
 * Both are best-effort. A mail failure must never cost the student the
 * submission they just made, so each is wrapped and only debugged out.
 *
 * Each message is written in its own recipient's language, not the language of
 * the request that triggered it — the applicant may apply in Arabic while the
 * reviewer reads English.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {

    /**
     * Fire both messages for a form that has just been sent.
     *
     * Call only for STATUS_SUBMITTED — a saved draft is not an application.
     *
     * @param object $jobform the activity record
     * @param object $cm course module
     * @param object $course
     * @param object $user the applicant
     * @param int $submissionid
     * @return void
     */
    public static function submission_sent(object $jobform, object $cm, object $course,
            object $user, int $submissionid): void {
        try {
            self::email_applicant($jobform, $course, $user);
        } catch (\Throwable $e) {
            debugging('mod_jobform: applicant acknowledgement failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
        try {
            self::notify_reviewers($jobform, $cm, $course, $user, $submissionid);
        } catch (\Throwable $e) {
            debugging('mod_jobform: reviewer notification failed: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }

    /**
     * "We have received your application" — the acknowledgement the applicant gets.
     *
     * @param object $jobform
     * @param object $course
     * @param object $user
     * @return bool true when the mail was handed to the mail system
     */
    private static function email_applicant(object $jobform, object $course, object $user): bool {
        if (!self::is_applicant_email_enabled() || !self::can_email($user)) {
            return false;
        }

        $lang = self::user_lang($user);
        $old = force_current_language($lang);
        try {
            $subject = get_string('ackemailsubject', 'mod_jobform');
            $a = (object) [
                'name'     => fullname($user),
                'activity' => format_string($jobform->name),
                'course'   => format_string($course->fullname),
            ];
            $html = self::wrap([
                s(get_string('ackemailgreeting', 'mod_jobform', $a->name)),
                s(get_string('ackemailbody', 'mod_jobform')),
                s(get_string('ackemaildetails', 'mod_jobform', $a)),
                s(get_string('ackemailfooter', 'mod_jobform')),
            ], $lang, $subject);

            return (bool) email_to_user(
                $user,
                \core_user::get_noreply_user(),
                $subject,
                html_to_text($html, 0),
                $html
            );
        } finally {
            force_current_language($old);
        }
    }

    /**
     * Tell the people who review this activity's submissions that one arrived.
     *
     * Recipients are whoever holds mod/jobform:viewsubmissions on the activity,
     * plus the site administrators — AC-4.20.8 asks for an administrator to be
     * told, and get_users_by_capability() does not return them, so a course
     * whose teacher reviews the applications would otherwise leave the admin
     * out. Keyed by user id, so somebody who is both is told once.
     *
     * @param object $jobform
     * @param object $cm
     * @param object $course
     * @param object $user the applicant
     * @param int $submissionid
     * @return void
     */
    private static function notify_reviewers(object $jobform, object $cm, object $course,
            object $user, int $submissionid): void {
        $context = \context_module::instance($cm->id);
        $recipients = get_users_by_capability($context, 'mod/jobform:viewsubmissions', 'u.*');
        foreach (get_admins() as $admin) {
            $recipients[$admin->id] = $admin;
        }
        // Drop the applicant, in case they hold the capability themselves.
        unset($recipients[$user->id]);

        $url = new \moodle_url('/mod/jobform/view_submission.php',
            ['id' => $cm->id, 'submissionid' => $submissionid]);
        $link = $url->out(false);

        foreach ($recipients as $recipient) {
            if (!self::can_email($recipient)) {
                continue;
            }
            $lang = self::user_lang($recipient);
            $old = force_current_language($lang);
            try {
                $a = (object) [
                    'applicant' => fullname($user),
                    'email'     => $user->email,
                    'activity'  => format_string($jobform->name),
                    'course'    => format_string($course->fullname),
                    'url'       => $link,
                ];
                $subject = get_string('adminemailsubject', 'mod_jobform', $a);
                $text = get_string('adminemailbody', 'mod_jobform', $a);

                $message = new \core\message\message();
                $message->component         = 'mod_jobform';
                $message->name              = 'submission';
                $message->userfrom          = \core_user::get_noreply_user();
                $message->userto            = $recipient;
                $message->subject           = $subject;
                $message->fullmessage       = $text;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml   = self::wrap([
                    nl2br(s($text)),
                    \html_writer::link($link, s(get_string('adminemaillink', 'mod_jobform'))),
                ], $lang, $subject);
                $message->smallmessage      = $subject;
                $message->notification      = 1;
                $message->courseid          = (int) $course->id;
                $message->contexturl        = $link;
                $message->contexturlname    = get_string('adminemaillink', 'mod_jobform');

                message_send($message);
            } finally {
                force_current_language($old);
            }
        }
    }

    /**
     * Wrap paragraphs in a minimal, mail-client-safe HTML shell.
     *
     * Inline styles only: mail clients strip <style> and do not lay out with
     * flex or grid. Arabic is flipped to RTL.
     *
     * @param string[] $paragraphs ready-to-insert HTML, one per paragraph
     * @param string $lang the recipient's language code
     * @param string $title document title / preheader
     * @return string a complete HTML document
     */
    private static function wrap(array $paragraphs, string $lang, string $title): string {
        $rtl = (strpos($lang, 'ar') === 0);
        $dir = $rtl ? 'rtl' : 'ltr';
        $align = $rtl ? 'right' : 'left';
        $font = $rtl
            ? "'Segoe UI', Tahoma, 'Noto Naskh Arabic', Arial, sans-serif"
            : "'Segoe UI', Helvetica, Arial, sans-serif";
        $sitename = format_string(get_site()->fullname);

        $body = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }
            $body .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#333333;">'
                . $paragraph . '</p>';
        }

        return '<!DOCTYPE html><html dir="' . $dir . '" lang="' . s($lang) . '"><head>'
            . '<meta charset="utf-8"><title>' . s($title) . '</title></head>'
            . '<body style="margin:0;padding:24px;background:#f4f5f7;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"'
            . ' style="max-width:600px;background:#ffffff;border-radius:8px;">'
            . '<tr><td dir="' . $dir . '" align="' . $align . '"'
            . ' style="padding:28px;font-family:' . $font . ';text-align:' . $align . ';">'
            . $body
            . '<p style="margin:22px 0 0;font-size:12px;color:#888888;">' . s($sitename) . '</p>'
            . '</td></tr></table></td></tr></table></body></html>';
    }

    /**
     * Is the applicant acknowledgement switched on?
     *
     * Public because the site's Event notifications screen reads it to draw the
     * switch, and there must be exactly one answer to "does this email go out" —
     * a second copy of the rule in the admin page is how the two drift apart.
     *
     * Defaults to on: an applicant who submits a form and hears nothing back
     * assumes it failed, which is the complaint this email exists to prevent.
     *
     * @return bool
     */
    public static function is_applicant_email_enabled(): bool {
        $value = get_config('mod_jobform', 'notifyapplicant');
        return ($value === false || $value === null) ? true : (bool) $value;
    }

    /**
     * Is there a real, reachable mailbox behind this user?
     *
     * @param object $user
     * @return bool
     */
    private static function can_email(object $user): bool {
        return !empty($user->id)
            && !empty($user->email)
            && empty($user->deleted)
            && empty($user->suspended)
            && !isguestuser($user);
    }

    /**
     * The language this recipient's message should be written in: their own, or
     * the site default when they have never chosen one.
     *
     * @param object $user
     * @return string a Moodle language code
     */
    private static function user_lang(object $user): string {
        global $CFG;
        $lang = trim((string) ($user->lang ?? ''));
        return $lang !== '' ? $lang : (string) $CFG->lang;
    }
}
