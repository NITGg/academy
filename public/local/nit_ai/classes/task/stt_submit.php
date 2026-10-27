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

namespace local_nit_ai\task;

/**
 * Send an uploaded lesson video to the transcription service.
 *
 * Out of the form's request on purpose: the upload to the service can take a
 * while for a long lesson, and a service that is down should mean a retry a
 * few minutes later, not a teacher whose save failed.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stt_submit extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sttsubmit', 'local_nit_ai');
    }

    /**
     * @return void
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        \local_nit_ai\stt\manager::submit((int) ($data->cmid ?? 0));
    }
}
