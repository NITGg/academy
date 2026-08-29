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

namespace local_profilefields\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_profilefields.
 *
 * The plugin's own settings are site-wide layout, and the field values themselves
 * belong to core - `user` columns and `user_info_data` rows - reported by core's
 * own providers. One table here does hold personal data: the log of registration
 * attempts the location guard refused, which records the IP address that tried.
 *
 * Those rows are declared in the metadata so the site's privacy register is
 * honest about them, but they are deliberately not returned for any subject
 * access or deletion request, and the plugin exports and deletes nothing: a
 * refused attempt never created an account, so there is no user id on the row and
 * no reliable way to attribute an address to a person. Guessing - handing over or
 * deleting every attempt that shares an IP with the requester - would leak other
 * people's attempts from the same network, which is worse than returning nothing.
 * An admin who wants the log gone empties it from the Register reports page.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describe what this plugin stores.
     *
     * @param collection $collection the metadata collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_profilefields_log', [
            'ip'          => 'privacy:metadata:local_profilefields_log:ip',
            'declared'    => 'privacy:metadata:local_profilefields_log:declared',
            'detected'    => 'privacy:metadata:local_profilefields_log:detected',
            'reason'      => 'privacy:metadata:local_profilefields_log:reason',
            'timecreated' => 'privacy:metadata:local_profilefields_log:timecreated',
        ], 'privacy:metadata:local_profilefields_log');

        return $collection;
    }

    /**
     * The contexts holding data for a user - always none; see the class comment.
     *
     * @param int $userid the user to look for
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Export the user's data. Nothing is attributable, so nothing is exported.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Delete everything in a context. Nothing is attributable, so nothing is deleted.
     *
     * @param \context $context the context to delete in
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete one user's data. Nothing is attributable, so nothing is deleted.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
