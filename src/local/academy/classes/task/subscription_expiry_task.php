<?php
namespace local_academy\task;

defined('MOODLE_INTERNAL') || die();

use local_academy\settings_manager;
use local_academy\notification_manager;
use local_academy\subscription_purchase_manager;

/**
 * Daily task for course-access subscriptions (US-SB-1-2 / US-AD-6-1):
 *
 *  1. Expire subscriptions whose `expires_at` has passed: flip active → expired and unenrol the
 *     student from any course no other active subscription of theirs still grants.
 *  2. Remind students whose subscription is within the admin-configured `expiry_reminder_days`
 *     window (once per purchase, tracked by `expiry_notified`).
 */
class subscription_expiry_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_subscription_expiry', 'local_academy');
    }

    public function execute() {
        global $DB;

        $now = time();

        // 1. Expire past-due active subscriptions + revoke course access.
        $expired = 0;
        $due = $DB->get_records_select('academy_sub_purchases',
            "status = :active AND expires_at > 0 AND expires_at <= :now",
            array('active' => 'active', 'now' => $now));
        foreach ($due as $p) {
            $DB->set_field('academy_sub_purchases', 'status', 'expired', array('id' => $p->id));
            // Revoke reads the DB for the user's other active subs, so update status first.
            subscription_purchase_manager::revoke_course_access($p->userid, $p->subscriptionid);
            $expired++;
        }

        // 2. Expiry reminders.
        $sent = 0;
        $days = (int) settings_manager::get('expiry_reminder_days');
        if ($days > 0) {
            $windowend = $now + ($days * DAYSECS);
            $sql = "SELECT sp.*, s.name AS subscription_name
                      FROM {academy_sub_purchases} sp
                      JOIN {academy_subscriptions} s ON s.id = sp.subscriptionid
                     WHERE sp.status = :active
                       AND sp.expires_at > :now
                       AND sp.expires_at <= :windowend
                       AND sp.expiry_notified = 0";
            $rows = $DB->get_records_sql($sql, array(
                'active'    => 'active',
                'now'       => $now,
                'windowend' => $windowend,
            ));
            foreach ($rows as $r) {
                $daysleft = (int) ceil(((int)$r->expires_at - $now) / DAYSECS);
                notification_manager::subscription_expiry_reminder($r, $daysleft);
                $DB->set_field('academy_sub_purchases', 'expiry_notified', $now, array('id' => $r->id));
                $sent++;
            }
        }

        mtrace("local_academy: expired {$expired} subscription(s), sent {$sent} expiry reminder(s).");
    }
}
