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

namespace local_msgrules\task;

use local_msgrules\sync;

/**
 * Re-derive the pairs for one account, queued by the observers.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_user extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksyncuser', 'local_msgrules');
    }

    /**
     * @return void
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();
        $userid = (int) ($data['userid'] ?? 0);
        if (!$userid) {
            return;
        }

        $result = sync::sync_user($userid);
        mtrace(sprintf(
            'local_msgrules: user %d - %d blocks added, %d removed, %d left to the user.',
            $userid,
            $result['added'],
            $result['removed'],
            $result['skipped']
        ));
    }
}
