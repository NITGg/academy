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

namespace local_msgrules;

/**
 * Keeps the derived block rows in step with the things they are derived from.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Put back a block the matrix still denies.
     *
     * The rules are enforced through the recipient's own blocked-users list, and core shows
     * that list to the user with a button to clear each entry. Left alone, any restricted
     * account could lift the site policy on itself from its own message preferences, so the
     * pair goes straight back - synchronously, because the gap between the two is a window
     * in which the message would actually go through.
     *
     * @param \core\event\message_user_unblocked $event
     * @return void
     */
    public static function message_user_unblocked(\core\event\message_user_unblocked $event): void {
        global $DB;

        $recipientid = (int) $event->userid;          // Owner of the block row.
        $senderid = (int) $event->relateduserid;      // The account that was let back in.

        if (!sync::should_block($recipientid, $senderid)) {
            // Either the plugin is off, one of them is exempt, or the matrix now permits it -
            // in which case the user is welcome to their unblock. Drop our claim on the pair.
            $DB->delete_records('local_msgrules_managed', [
                'userid'        => $recipientid,
                'blockeduserid' => $senderid,
            ]);
            return;
        }

        $row = [
            'userid'        => $recipientid,
            'blockeduserid' => $senderid,
        ];

        if (!$DB->record_exists('message_users_blocked', $row)) {
            $DB->insert_record('message_users_blocked', (object) ($row + ['timecreated' => time()]));
        }
        if (!$DB->record_exists('local_msgrules_managed', $row)) {
            $DB->insert_record('local_msgrules_managed', (object) ($row + ['timecreated' => time()]));
        }
    }

    /**
     * Re-derive one account's pairs after its cohort membership moved.
     *
     * Queued rather than run inline: a cohort upload or a sync plugin can move hundreds of
     * members in one request, and each move costs a pass over the roster. The adhoc queue
     * collapses repeats for the same user, so a bulk change settles in one pass per account.
     *
     * @param \core\event\base $event cohort_member_added or cohort_member_removed.
     * @return void
     */
    public static function cohort_membership_changed(\core\event\base $event): void {
        self::queue_user((int) $event->relateduserid);
    }

    /**
     * Derive a brand-new account's pairs.
     *
     * A new account is in no cohort, so under a matrix that says nothing about "no cohort" it
     * is denied everything - but only once the rows exist. Waiting for the nightly rebuild
     * would leave it able to write to the whole site until then.
     *
     * @param \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event): void {
        self::queue_user((int) $event->objectid);
    }

    /**
     * Forget an account that no longer exists.
     *
     * Core clears the deleted user's own message_users_blocked rows, so all that is left to
     * do is drop our record of the ones we owned - in both directions, since the account was
     * both a recipient and a sender.
     *
     * @param \core\event\user_deleted $event
     * @return void
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $userid = (int) $event->objectid;
        $DB->delete_records('local_msgrules_managed', ['userid' => $userid]);
        $DB->delete_records('local_msgrules_managed', ['blockeduserid' => $userid]);
        $DB->delete_records('message_users_blocked', ['blockeduserid' => $userid]);
    }

    /**
     * Ask cron to re-derive one account, unless the plugin is switched off.
     *
     * @param int $userid
     * @return void
     */
    private static function queue_user(int $userid): void {
        if (!$userid || !rules::is_enabled()) {
            return;
        }

        $task = new task\sync_user();
        $task->set_custom_data(['userid' => $userid]);
        $task->set_component('local_msgrules');

        // The second argument drops a duplicate that is already waiting, so a member moved
        // between five cohorts in one upload still costs one pass.
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
