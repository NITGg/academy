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

/**
 * Privacy class for requesting user data.
 *
 * @package    profilefield_file
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace profilefield_file\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for the file profile field.
 *
 * Unlike the text-based field types this one owns files as well as rows, so every
 * export/delete path handles the user-context file area alongside user_info_data.
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /** @var string The datatype this plugin owns. */
    const DATATYPE = 'file';

    /**
     * Describe what this plugin stores.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('user_info_data', [
            'userid'     => 'privacy:metadata:profilefield_file:userid',
            'fieldid'    => 'privacy:metadata:profilefield_file:fieldid',
            'data'       => 'privacy:metadata:profilefield_file:data',
            'dataformat' => 'privacy:metadata:profilefield_file:dataformat',
        ], 'privacy:metadata:profilefield_file:tableexplanation');

        $collection->add_subsystem_link('core_files', [],
            'privacy:metadata:profilefield_file:filepurpose');

        return $collection;
    }

    /**
     * The contexts holding data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                  JOIN {context} ctx ON ctx.instanceid = uda.userid
                       AND ctx.contextlevel = :contextlevel
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'userid'       => $userid,
            'contextlevel' => CONTEXT_USER,
            'datatype'     => self::DATATYPE,
        ]);

        return $contextlist;
    }

    /**
     * The users holding data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_user) {
            return;
        }

        $sql = "SELECT uda.userid
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype";

        $userlist->add_from_sql('userid', $sql, [
            'userid'   => $context->instanceid,
            'datatype' => self::DATATYPE,
        ]);
    }

    /**
     * Export the field data and the uploaded files.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_USER || $context->instanceid != $user->id) {
                continue;
            }

            foreach (self::get_records($user->id) as $record) {
                $subcontext = [get_string('pluginname', 'profilefield_file'), $record->name];

                writer::with_context($context)->export_data($subcontext, (object) [
                    'name'        => $record->name,
                    'description' => $record->description,
                    'data'        => $record->data,
                ]);

                // The file itself, not just its name.
                writer::with_context($context)->export_area_files($subcontext,
                    'profilefield_file', 'files', $record->fieldid);
            }
        }
    }

    /**
     * Delete everything in a user context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        if ($context->contextlevel == CONTEXT_USER) {
            self::delete_data($context->instanceid);
        }
    }

    /**
     * Delete for approved users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            self::delete_data($context->instanceid);
        }
    }

    /**
     * Delete for one user across approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $user->id) {
                self::delete_data($context->instanceid);
            }
        }
    }

    /**
     * Remove a user's file-field rows and the files behind them.
     *
     * @param int $userid
     */
    protected static function delete_data(int $userid) {
        global $DB;

        $context = \context_user::instance($userid, IGNORE_MISSING);
        if ($context) {
            $fs = get_file_storage();
            foreach (self::get_records($userid) as $record) {
                $fs->delete_area_files($context->id, 'profilefield_file', 'files', $record->fieldid);
            }
        }

        $DB->delete_records_select('user_info_data',
            'userid = :userid AND fieldid IN (
                 SELECT id FROM {user_info_field} WHERE datatype = :datatype)',
            ['userid' => $userid, 'datatype' => self::DATATYPE]);
    }

    /**
     * This plugin's user_info_data rows for a user, joined to their field.
     *
     * @param int $userid
     * @return array
     */
    protected static function get_records(int $userid): array {
        global $DB;

        $sql = "SELECT uda.id, uda.fieldid, uda.data, uif.name, uif.description
                  FROM {user_info_data} uda
                  JOIN {user_info_field} uif ON uda.fieldid = uif.id
                 WHERE uda.userid = :userid
                       AND uif.datatype = :datatype";

        return $DB->get_records_sql($sql, ['userid' => $userid, 'datatype' => self::DATATYPE]);
    }
}
