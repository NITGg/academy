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
 * Fill in the playing time of videos VdoCipher had not finished processing yet.
 *
 * A video's length is not known at upload time: VdoCipher reports it only once
 * transcoding finishes, minutes later, and nothing calls back to tell us. So the
 * row starts at length 0 and this task walks the ones still at 0 and asks.
 *
 * Running in cron rather than on the course page is the point — by the time a
 * learner opens the course, the number is already stored and the page costs no
 * API call at all.
 *
 * @package    local_vdocipher
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_vdocipher\task;

defined('MOODLE_INTERNAL') || die();

use local_vdocipher\api_client;
use local_vdocipher\video_service;

/**
 * Scheduled task: refresh the stored length of videos that do not have one yet.
 */
class refresh_lengths extends \core\task\scheduled_task {

    /** @var int How many videos one run may ask about. */
    const BATCH = 20;

    /** @var int Network timeout per video, so an unreachable API bounds the run. */
    const TIMEOUT = 15;

    /**
     * Name shown in Site administration → Server → Scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_refreshlengths', 'local_vdocipher');
    }

    /**
     * Ask VdoCipher about a batch of videos whose length is still unknown.
     */
    public function execute() {
        if (!api_client::is_configured()) {
            mtrace('local_vdocipher: no API secret configured — nothing to refresh.');
            return;
        }

        $rows = video_service::rows_missing_length(self::BATCH);
        if (!$rows) {
            return;
        }

        $filled = 0;
        foreach ($rows as $row) {
            // fetch_length swallows API errors and stamps the row either way, so
            // one unreachable video cannot stop the rest of the batch.
            if (video_service::fetch_length($row, self::TIMEOUT) > 0) {
                $filled++;
            }
        }

        mtrace('local_vdocipher: asked about ' . count($rows) . ' video(s), '
            . $filled . ' now have a playing time.');
    }
}
