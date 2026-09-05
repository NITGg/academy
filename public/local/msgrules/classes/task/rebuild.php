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
 * An on-demand rebuild, queued when the matrix is saved or the feature is switched on or off.
 *
 * Queued rather than run in the request that saved the form: a rebuild touches one row per
 * denied direction, which on a few hundred accounts is already more work than a web request
 * should carry, and a timeout halfway through would leave the site half-restricted.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rebuild extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksyncblocks', 'local_msgrules');
    }

    /**
     * @return void
     */
    public function execute(): void {
        sync::rebuild(new \text_progress_trace());
    }

    /**
     * Put a rebuild in the queue, collapsing it with one that is already waiting.
     *
     * @return void
     */
    public static function queue(): void {
        $task = new self();
        $task->set_component('local_msgrules');
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
