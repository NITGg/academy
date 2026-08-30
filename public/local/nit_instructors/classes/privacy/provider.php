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

namespace local_nit_instructors\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_nit_instructors\profile;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_nit_instructors.
 *
 * The data here is unusual for a privacy provider: an instructor's background is
 * written *in order to be published*. It is still personal data, and a subject
 * access request has to return it, but the plugin holds nothing the instructor did
 * not type about themselves and intend other people to read.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe what is stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(profile::TABLE, [
            'userid' => 'privacy:metadata:local_nit_instructors_profile:userid',
            'specialtyen' => 'privacy:metadata:local_nit_instructors_profile:specialtyen',
            'specialtyar' => 'privacy:metadata:local_nit_instructors_profile:specialtyar',
            'years' => 'privacy:metadata:local_nit_instructors_profile:years',
            'status' => 'privacy:metadata:local_nit_instructors_profile:status',
            'decisionnote' => 'privacy:metadata:local_nit_instructors_profile:decisionnote',
        ], 'privacy:metadata:local_nit_instructors_profile');

        $collection->add_database_table(profile::ENTRY_TABLE, [
            'titleen' => 'privacy:metadata:local_nit_instructors_entry:titleen',
            'titlear' => 'privacy:metadata:local_nit_instructors_entry:titlear',
            'orgen' => 'privacy:metadata:local_nit_instructors_entry:orgen',
            'orgar' => 'privacy:metadata:local_nit_instructors_entry:orgar',
            'perioden' => 'privacy:metadata:local_nit_instructors_entry:perioden',
            'periodar' => 'privacy:metadata:local_nit_instructors_entry:periodar',
        ], 'privacy:metadata:local_nit_instructors_entry');

        return $collection;
    }

    /**
     * The contexts holding data for a user.
     *
     * A background belongs to a person rather than to a course, so it lives in
     * their own user context.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists(profile::TABLE, ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }

        return $contextlist;
    }

    /**
     * The users with data in a context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context instanceof \context_user) {
            $userlist->add_user($context->instanceid);
        }
    }

    /**
     * Export everything held about the users in these contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_user) || $context->instanceid != $userid) {
                continue;
            }

            $versions = $DB->get_records(profile::TABLE, ['userid' => $userid], 'timecreated ASC');
            if (!$versions) {
                continue;
            }

            $export = [];
            foreach ($versions as $version) {
                $entries = [];
                foreach (profile::entries((int) $version->id) as $type => $rows) {
                    foreach ($rows as $row) {
                        $entries[] = [
                            'type' => $type,
                            'title_en' => $row->titleen,
                            'title_ar' => $row->titlear,
                            'organisation_en' => $row->orgen,
                            'organisation_ar' => $row->orgar,
                            'period_en' => $row->perioden,
                            'period_ar' => $row->periodar,
                        ];
                    }
                }

                $export[] = [
                    'status' => $version->status,
                    'specialty_en' => $version->specialtyen,
                    'specialty_ar' => $version->specialtyar,
                    'years_of_experience' => (int) $version->years,
                    'decision_note' => $version->decisionnote,
                    'created' => transform::datetime($version->timecreated),
                    'entries' => $entries,
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_nit_instructors')],
                (object) ['versions' => $export]
            );
        }
    }

    /**
     * Delete everything in a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            profile::purge((int) $context->instanceid);
        }
    }

    /**
     * Delete everything held for one user.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && $context->instanceid == $userid) {
                profile::purge($userid);
            }
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();

        if (!($context instanceof \context_user)) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            if ($userid == $context->instanceid) {
                profile::purge((int) $userid);
            }
        }
    }
}
