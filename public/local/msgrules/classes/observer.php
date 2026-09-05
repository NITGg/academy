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
 * Keeps the derived block rows in step with enrolments, roles and courses.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Put back a block a course still requires.
     *
     * The rules are enforced through the recipient's own blocked-users list, and core shows
     * that list to the user with a button to clear each entry. Left alone, any restricted
     * student could lift the policy on themselves from their own message preferences, so the
     * pair goes straight back - synchronously, because the gap between the two would be a
     * window in which the message actually went through.
     *
     * @param \core\event\message_user_unblocked $event
     * @return void
     */
    public static function message_user_unblocked(\core\event\message_user_unblocked $event): void {
        global $DB;

        $recipientid = (int) $event->userid;          // Owner of the block row.
        $senderid = (int) $event->relateduserid;      // The account that was let back in.

        if (!sync::should_block($recipientid, $senderid)) {
            // Either the plugin is off, one of them is exempt, or the courses now permit it -
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
     * Re-derive one account's pairs after an enrolment changed.
     *
     * @param \core\event\base $event One of the user_enrolment_* events.
     * @return void
     */
    public static function enrolment_changed(\core\event\base $event): void {
        self::queue_user((int) $event->relateduserid);
    }

    /**
     * Re-derive one account's pairs after a role was assigned or taken away.
     *
     * Only course, category and system contexts matter - those are the ones the teacher
     * lookup walks. A role on an activity or a block changes nothing here.
     *
     * @param \core\event\base $event role_assigned or role_unassigned.
     * @return void
     */
    public static function role_changed(\core\event\base $event): void {
        $context = $event->get_context();
        if (!in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_COURSECAT, CONTEXT_SYSTEM], true)) {
            return;
        }

        self::queue_user((int) $event->relateduserid);
    }

    /**
     * Derive the pairs for a brand-new account.
     *
     * A new account is on no course, so every restricted student on the site must be kept out
     * of it. Those rows do not exist until somebody makes them, and waiting for the nightly
     * rebuild would leave the account reachable by everyone until then.
     *
     * @param \core\event\user_created $event
     * @return void
     */
    public static function user_created(\core\event\user_created $event): void {
        self::queue_user((int) $event->objectid);
    }

    /**
     * Forget a deleted course's override.
     *
     * @param \core\event\course_deleted $event
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('local_msgrules_course', ['courseid' => (int) $event->objectid]);

        // Its students may now be unrestricted, and their rows are nobody's job but ours.
        if (rules::is_enabled()) {
            task\rebuild::queue();
        }
    }

    /**
     * Forget an account that no longer exists.
     *
     * Core clears the deleted user's own message_users_blocked rows, so all that is left is to
     * drop our record of the ones we owned - in both directions, since the account was both a
     * recipient and a sender.
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
     * Queued rather than run inline: enrolling a cohort or uploading a CSV moves hundreds of
     * people in one request, and each move costs a pass over the roster. The adhoc queue drops
     * a duplicate that is already waiting, so a bulk change settles in one pass per account.
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

        \core\task\manager::queue_adhoc_task($task, true);
    }
}
