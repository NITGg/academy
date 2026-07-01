<?php
namespace local_academy\task;

defined('MOODLE_INTERNAL') || die();

use local_academy\settings_manager;
use local_academy\notification_manager;

/**
 * Daily task: warn students whose package is about to expire.
 *
 * A student is reminded once, when an active purchase with Flex left is within the
 * admin-configured `expiry_reminder_days` window of its `expires_at`. The `expiry_notified`
 * column stops the same purchase being notified again on the next run.
 */
class expiry_reminder_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_expiry_reminder', 'local_academy');
    }

    public function execute() {
        global $DB;

        $days = (int) settings_manager::get('expiry_reminder_days');
        if ($days <= 0) {
            return; // Reminder disabled.
        }

        $now = time();
        $windowend = $now + ($days * DAYSECS);

        // Active purchases that still have Flex, expire inside the window, and haven't been
        // reminded yet. expires_at > now excludes already-expired packages.
        $sql = "SELECT pu.*, p.name AS package_name
                  FROM {academy_package_purchases} pu
                  JOIN {academy_packages} p ON p.id = pu.packageid
                 WHERE pu.status = :active
                   AND pu.remaining_flex > 0
                   AND pu.expires_at > :now
                   AND pu.expires_at <= :windowend
                   AND pu.expiry_notified = 0";
        $rows = $DB->get_records_sql($sql, array(
            'active'    => 'active',
            'now'       => $now,
            'windowend' => $windowend,
        ));

        $sent = 0;
        foreach ($rows as $r) {
            $daysleft = (int) ceil(((int)$r->expires_at - $now) / DAYSECS);
            notification_manager::package_expiry_reminder($r, $daysleft);
            $DB->set_field('academy_package_purchases', 'expiry_notified', $now, array('id' => $r->id));
            $sent++;
        }

        mtrace("local_academy: sent {$sent} package-expiry reminder(s).");
    }
}
