<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Lesson-lifecycle notifications (in-app + email).
 *
 * Every teacher/student step in {@see lesson_manager} notifies the other party (and, for a
 * teacher-absence report, the platform admins). Delivery goes through Moodle's
 * {@see message_send()} on the `local_academy/lessonnotification` provider, so each message:
 *   - lands in {notifications} → shown in the web notification bell AND returned by the
 *     mobile `get_notifications` endpoint (academyApi/json.php :: get_user_notifications);
 *   - is emailed by the email processor per the recipient's message preferences.
 *
 * Best-effort, like {@see audit_manager}: a failed notification never breaks the lesson
 * action that triggered it.
 */
class notification_manager {

    /**
     * Notify one user about a lesson event.
     *
     * Subject/body come from lang strings `notif_{$key}_subject` / `notif_{$key}_body`, both
     * rendered with a shared placeholder bag built from the lesson (subject, student/teacher
     * names, formatted time, note) plus anything in $extra (reason, actor, time override).
     *
     * @param object $lesson      academy_lessons record
     * @param string $key         event key, e.g. 'requested', 'confirmed_by_teacher'
     * @param int    $recipientid user to notify
     * @param int    $fromid      acting user (0 → system / no-reply)
     * @param array  $extra       optional overrides: 'time' (unix), 'reason', 'actor'
     */
    public static function lesson_event($lesson, $key, $recipientid, $fromid = 0, array $extra = array()) {
        global $DB, $CFG;
        try {
            $recipientid = (int)$recipientid;
            if ($recipientid <= 0) {
                return;
            }
            $recipient = $DB->get_record('user', array('id' => $recipientid, 'deleted' => 0, 'suspended' => 0));
            if (!$recipient) {
                return;
            }

            $student = $DB->get_record('user', array('id' => $lesson->studentid), 'id, firstname, lastname');
            $teacher = $DB->get_record('user', array('id' => $lesson->teacherid), 'id, firstname, lastname');

            // Default to the confirmed time, falling back to the originally requested time;
            // callers pass an explicit 'time' for suggestions / reschedules.
            $time = isset($extra['time']) ? (int)$extra['time']
                : ((int)$lesson->confirmed_time > 0 ? (int)$lesson->confirmed_time : (int)$lesson->requested_time);

            $a = (object) array(
                'subject' => format_string($lesson->subject),
                'student' => $student ? fullname($student) : '',
                'teacher' => $teacher ? fullname($teacher) : '',
                'time'    => $time > 0 ? userdate($time) : '',
                'note'    => isset($lesson->note) ? trim((string)$lesson->note) : '',
                'reason'  => isset($extra['reason']) ? trim((string)$extra['reason']) : '',
                'actor'   => isset($extra['actor']) ? (string)$extra['actor'] : '',
            );

            $subject = get_string("notif_{$key}_subject", 'local_academy', $a);
            $body    = get_string("notif_{$key}_body", 'local_academy', $a);

            // Recipients open their own management page: teachers → my_lessons, everyone else → student hub.
            $page = ((int)$recipientid === (int)$lesson->teacherid)
                ? '/local/academy/my_lessons.php' : '/local/academy/student.php';
            $url = new \moodle_url($page);

            $message = new \core\message\message();
            $message->component         = 'local_academy';
            $message->name              = 'lessonnotification';
            $message->userfrom          = $fromid > 0 ? \core_user::get_user($fromid) : \core_user::get_noreply_user();
            $message->userto            = $recipient;
            $message->subject           = $subject;
            $message->fullmessage       = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = '<p>' . s($body) . '</p>';
            $message->smallmessage      = $subject;
            $message->notification      = 1;
            $message->contexturl        = $url->out(false);
            $message->contexturlname    = get_string('mylessons', 'local_academy');

            // Buffer message_send: a mis-configured mail server makes the email processor *print*
            // a warning (it does not throw), which would otherwise corrupt the JSON API response
            // and surface to the app as "Session expired". Capture and discard any such output.
            ob_start();
            try {
                message_send($message);
            } finally {
                ob_end_clean();
            }
        } catch (\Throwable $e) {
            // Notifications must never block the lesson action; swallow and move on.
            debugging('academy notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Remind a student that a package is about to expire.
     *
     * Sent by {@see \local_academy\task\expiry_reminder_task} when a purchase is within the
     * admin-configured `expiry_reminder_days` window. Delivered on the `expirynotification`
     * provider so it lands in the web bell + mobile `get_notifications` pull and is emailed.
     * Best-effort, like {@see lesson_event}: a failure never breaks the cron run.
     *
     * @param object $purchase academy_package_purchases record joined with `package_name`
     * @param int    $daysleft whole days remaining until expiry (shown to the student)
     */
    public static function package_expiry_reminder($purchase, $daysleft) {
        global $DB;
        try {
            $recipient = $DB->get_record('user', array('id' => $purchase->userid, 'deleted' => 0, 'suspended' => 0));
            if (!$recipient) {
                return;
            }

            $a = (object) array(
                'package'   => isset($purchase->package_name) ? format_string($purchase->package_name) : '',
                'days'      => (int)$daysleft,
                'date'      => (int)$purchase->expires_at > 0 ? userdate((int)$purchase->expires_at, get_string('strftimedate', 'langconfig')) : '',
                'flex'      => (int)$purchase->remaining_flex,
            );

            $url = new \moodle_url('/local/academy/student.php');

            $message = new \core\message\message();
            $message->component         = 'local_academy';
            $message->name              = 'expirynotification';
            $message->userfrom          = \core_user::get_noreply_user();
            $message->userto            = $recipient;
            $message->subject           = get_string('notif_package_expiring_subject', 'local_academy', $a);
            $message->fullmessage       = get_string('notif_package_expiring_body', 'local_academy', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = '<p>' . s($message->fullmessage) . '</p>';
            $message->smallmessage      = $message->subject;
            $message->notification      = 1;
            $message->contexturl        = $url->out(false);
            $message->contexturlname    = get_string('mypackages', 'local_academy');

            // See lesson_event: buffer to swallow any warning the mail processor prints.
            ob_start();
            try {
                message_send($message);
            } finally {
                ob_end_clean();
            }
        } catch (\Throwable $e) {
            debugging('academy expiry reminder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Notify every platform admin (manageplatform capability) about a lesson event.
     * Used for teacher-absence reports (US-LS-3-4).
     */
    public static function lesson_event_admins($lesson, $key, $fromid = 0, array $extra = array()) {
        try {
            $context = \context_system::instance();
            $admins = get_users_by_capability($context, 'local/academy:manageplatform', 'u.id');
            foreach ($admins as $admin) {
                self::lesson_event($lesson, $key, $admin->id, $fromid, $extra);
            }
        } catch (\Throwable $e) {
            debugging('academy admin notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
