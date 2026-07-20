<?php
namespace local_academy\task;

defined('MOODLE_INTERNAL') || die();

use local_academy\settings_manager;
use local_academy\notification_manager;
use local_academy\program_purchase_manager;

/**
 * Daily task: warn students whose access to a paid program is about to expire.
 *
 * enrol_programs stamps each allocation with a `timeend` (access end date; the program's own
 * tables are never modified, see {@see program_purchase_manager}). A student is reminded once,
 * when a non-archived allocation's `timeend` falls inside the admin-configured
 * `program_expiry_reminder_days` window. Since we cannot add an `expiry_notified` column to the
 * third-party `enrol_programs_allocations` table, "already reminded" is tracked in our own
 * `academy_program_notif` table instead.
 *
 * enrol_programs already ships its OWN "ending soon" reminder (a per-program `notifyendsoon`
 * checkbox, fixed 3-day window, driven by its own cron task) — see
 * \enrol_programs\local\notification::notify_endsoon(). Sending ours on top of that would double
 * up the reminder for any program with that checkbox on, so this task only picks up allocations
 * whose program has `notifyendsoon` OFF: one reminder per program, from whichever system owns it.
 */
class program_expiry_reminder_task extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_program_expiry_reminder', 'local_academy');
    }

    public function execute() {
        global $DB;

        if (!program_purchase_manager::available()) {
            return;
        }

        $days = (int) settings_manager::get('program_expiry_reminder_days');
        if ($days <= 0) {
            return; // Reminder disabled.
        }

        $now = time();
        $windowend = $now + ($days * DAYSECS);

        // Non-archived, not-yet-completed allocations with a timeend inside the window, on a program
        // whose own "notify end soon" checkbox is off (see class docblock), that haven't been
        // reminded by us yet. timeend > now excludes allocations that already closed.
        $sql = "SELECT a.*, p.fullname AS program_name
                  FROM {enrol_programs_allocations} a
                  JOIN {enrol_programs_programs} p ON p.id = a.programid
             LEFT JOIN {academy_program_notif} n ON n.allocationid = a.id
                 WHERE a.archived = 0
                   AND p.archived = 0
                   AND p.notifyendsoon = 0
                   AND a.timecompleted IS NULL
                   AND a.timeend > :now
                   AND a.timeend <= :windowend
                   AND n.id IS NULL";
        $rows = $DB->get_records_sql($sql, array(
            'now'       => $now,
            'windowend' => $windowend,
        ));

        $sent = 0;
        foreach ($rows as $r) {
            $daysleft = (int) ceil(((int)$r->timeend - $now) / DAYSECS);
            notification_manager::program_expiry_reminder($r, $daysleft);
            $DB->insert_record('academy_program_notif', (object) array(
                'allocationid' => $r->id,
                'timenotified' => $now,
            ));
            $sent++;
        }

        mtrace("local_academy: sent {$sent} program-expiry reminder(s).");
    }
}
