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

namespace local_appleauth\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the Apple ID linked to each account, in the user's context.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe the stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_appleauth_links', [
            'userid' => 'privacy:metadata:local_appleauth_links:userid',
            'sub' => 'privacy:metadata:local_appleauth_links:sub',
            'email' => 'privacy:metadata:local_appleauth_links:email',
            'timecreated' => 'privacy:metadata:local_appleauth_links:timecreated',
            'timemodified' => 'privacy:metadata:local_appleauth_links:timemodified',
        ], 'privacy:metadata:local_appleauth_links');
        return $collection;
    }

    /**
     * The user's own context, when they have an Apple link.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id FROM {context} ctx
               JOIN {local_appleauth_links} l ON l.userid = ctx.instanceid
              WHERE ctx.contextlevel = :level AND l.userid = :userid",
            ['level' => CONTEXT_USER, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_user) {
            $userlist->add_from_sql('userid', "SELECT userid FROM {local_appleauth_links} WHERE userid = :userid",
                ['userid' => $context->instanceid]);
        }
    }

    /**
     * Export the user's Apple link.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || $context->instanceid != $contextlist->get_user()->id) {
                continue;
            }
            $links = $DB->get_records('local_appleauth_links', ['userid' => $context->instanceid], '', 'sub, email, timecreated, timemodified');
            if ($links) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_appleauth')], (object) ['links' => array_values($links)]);
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_user) {
            $DB->delete_records('local_appleauth_links', ['userid' => $context->instanceid]);
        }
    }

    /**
     * Delete one user's data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $DB->delete_records('local_appleauth_links', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Delete several users' data in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        if ($userlist->get_context() instanceof \context_user) {
            $DB->delete_records_list('local_appleauth_links', 'userid', $userlist->get_userids());
        }
    }
}
