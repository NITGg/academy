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

namespace local_msgrules\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy metadata for local_msgrules.
 *
 * The plugin stores no message content and no profile data. Its one table records which of
 * the entries in a user's blocked-users list were placed by site policy rather than by the
 * user, which is a fact about a pair of accounts and is therefore declared.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_msgrules_managed',
            [
                'userid'        => 'privacy:metadata:local_msgrules_managed:userid',
                'blockeduserid' => 'privacy:metadata:local_msgrules_managed:blockeduserid',
                'timecreated'   => 'privacy:metadata:local_msgrules_managed:timecreated',
            ],
            'privacy:metadata:local_msgrules_managed'
        );

        return $collection;
    }
}
