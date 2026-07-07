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

            // Render the notification in the RECIPIENT's language, not the acting user's. Strings are
            // built with get_string/userdate, which follow current_language() — the actor's session
            // language — so without this a student browsing in English would send an English message
            // to an Arabic-preferring teacher (and vice versa). force_current_language() also localises
            // the userdate() below; restore the previous value in the finally so we never leak it.
            $reciplang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $prevforcelang = force_current_language($reciplang);
            try {
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
            } finally {
                force_current_language($prevforcelang);
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
        global $DB, $CFG;
        try {
            $recipient = $DB->get_record('user', array('id' => $purchase->userid, 'deleted' => 0, 'suspended' => 0));
            if (!$recipient) {
                return;
            }

            // Render in the student's language (see lesson_event): this runs from cron, where
            // current_language() is the site default, not the recipient's preference.
            $reciplang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $prevforcelang = force_current_language($reciplang);
            try {
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
            } finally {
                force_current_language($prevforcelang);
            }
        } catch (\Throwable $e) {
            debugging('academy expiry reminder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Remind a student that a subscription is about to expire.
     *
     * Sent by {@see \local_academy\task\subscription_expiry_task} when a purchase is within the
     * admin-configured `expiry_reminder_days` window. Delivered on the `expirynotification`
     * provider (web bell + mobile `get_notifications` pull + email). Best-effort.
     *
     * @param object $purchase academy_sub_purchases record joined with `subscription_name`
     * @param int    $daysleft whole days remaining until expiry (shown to the student)
     */
    public static function subscription_expiry_reminder($purchase, $daysleft) {
        global $DB, $CFG;
        try {
            $recipient = $DB->get_record('user', array('id' => $purchase->userid, 'deleted' => 0, 'suspended' => 0));
            if (!$recipient) {
                return;
            }

            // Render in the student's language (see lesson_event): this runs from cron, where
            // current_language() is the site default, not the recipient's preference.
            $reciplang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $prevforcelang = force_current_language($reciplang);
            try {
                $a = (object) array(
                    'subscription' => isset($purchase->subscription_name) ? format_string($purchase->subscription_name) : '',
                    'days'         => (int)$daysleft,
                    'date'         => (int)$purchase->expires_at > 0
                        ? userdate((int)$purchase->expires_at, get_string('strftimedate', 'langconfig')) : '',
                );

                $url = new \moodle_url('/local/academy/student.php');

                $message = new \core\message\message();
                $message->component         = 'local_academy';
                $message->name              = 'expirynotification';
                $message->userfrom          = \core_user::get_noreply_user();
                $message->userto            = $recipient;
                $message->subject           = get_string('notif_subscription_expiring_subject', 'local_academy', $a);
                $message->fullmessage       = get_string('notif_subscription_expiring_body', 'local_academy', $a);
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml   = '<p>' . s($message->fullmessage) . '</p>';
                $message->smallmessage      = $message->subject;
                $message->notification      = 1;
                $message->contexturl        = $url->out(false);
                $message->contexturlname    = get_string('mysubscriptions', 'local_academy');

                // See lesson_event: buffer to swallow any warning the mail processor prints.
                ob_start();
                try {
                    message_send($message);
                } finally {
                    ob_end_clean();
                }
            } finally {
                force_current_language($prevforcelang);
            }
        } catch (\Throwable $e) {
            debugging('academy subscription expiry reminder failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // B2B subscription notifications (US-B2B-1-*)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Send a B2B notification to one user on the `b2bnotification` provider.
     * Subject/body come from `notif_{$key}_subject` / `notif_{$key}_body`, rendered in the
     * recipient's language. Best-effort (never breaks the triggering action).
     *
     * @param int    $recipientid user to notify
     * @param string $key         event key, e.g. 'b2b_approved'
     * @param object $a           placeholder bag for the lang strings
     * @param int    $fromid      acting user (0 → no-reply)
     * @param string $page        target page for the notification link
     */
    protected static function b2b_send($recipientid, $key, $a, $fromid = 0, $page = '/local/academy/student.php') {
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
            $reciplang = !empty($recipient->lang) ? $recipient->lang : $CFG->lang;
            $prevforcelang = force_current_language($reciplang);
            try {
                $subject = get_string("notif_{$key}_subject", 'local_academy', $a);
                $body    = get_string("notif_{$key}_body", 'local_academy', $a);
                $url = new \moodle_url($page);

                $message = new \core\message\message();
                $message->component         = 'local_academy';
                $message->name              = 'b2bnotification';
                $message->userfrom          = $fromid > 0 ? \core_user::get_user($fromid) : \core_user::get_noreply_user();
                $message->userto            = $recipient;
                $message->subject           = $subject;
                $message->fullmessage       = $body;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml   = '<p>' . s($body) . '</p>';
                $message->smallmessage      = $subject;
                $message->notification      = 1;
                $message->contexturl        = $url->out(false);
                $message->contexturlname    = get_string('managesubscriptions', 'local_academy');

                ob_start();
                try {
                    message_send($message);
                } finally {
                    ob_end_clean();
                }
            } finally {
                force_current_language($prevforcelang);
            }
        } catch (\Throwable $e) {
            debugging('academy b2b notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /** Build the shared placeholder bag ({subscription, seats}) for a B2B purchase/membership record. */
    protected static function b2b_plan_bag($rec) {
        global $DB;
        $name = $DB->get_field('academy_subscriptions', 'name', array('id' => $rec->subscriptionid));
        return (object) array(
            'subscription' => $name ? format_string($name) : '',
            'seats'        => (int)($rec->seats ?? 0),
        );
    }

    /** Confirm a completed B2B purchase to the buyer (US-B2B-1-1). */
    public static function b2b_purchase_confirmed($purchase) {
        self::b2b_send($purchase->userid, 'b2b_purchased', self::b2b_plan_bag($purchase), 0,
            '/local/academy/b2b_dashboard.php');
    }

    /** Tell the B2B admin a user is waiting for approval (US-B2B-1-4 manual path). */
    public static function b2b_membership_pending($membership) {
        $a = self::b2b_plan_bag($membership);
        $a->user = self::user_name($membership->userid);
        self::b2b_send($membership->b2b_admin_id, 'b2b_pending', $a, 0, '/local/academy/b2b_dashboard.php');
    }

    /** Tell the invited user they were approved (US-B2B-1-5). */
    public static function b2b_membership_approved($membership) {
        self::b2b_send($membership->userid, 'b2b_approved', self::b2b_plan_bag($membership), $membership->approved_by);
    }

    /** Tell the invited user their request was rejected (US-B2B-1-6). */
    public static function b2b_membership_rejected($membership) {
        $a = self::b2b_plan_bag($membership);
        $a->reason = isset($membership->reject_reason) ? trim((string)$membership->reject_reason) : '';
        self::b2b_send($membership->userid, 'b2b_rejected', $a, $membership->b2b_admin_id);
    }

    /** Tell the user they were removed from the B2B subscription (US-B2B-1-7). */
    public static function b2b_member_removed($membership) {
        self::b2b_send($membership->userid, 'b2b_removed', self::b2b_plan_bag($membership), $membership->removed_by);
    }

    /** Full name for a user id (empty string if missing). */
    protected static function user_name($userid) {
        global $DB;
        $u = $DB->get_record('user', array('id' => $userid), 'id, firstname, lastname');
        return $u ? fullname($u) : '';
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
