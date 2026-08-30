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

namespace local_profilefields\task;

use local_profilefields\rememberme;

defined('MOODLE_INTERNAL') || die();

/**
 * Sweep up "Remember me" tokens that have passed their expiry.
 *
 * Housekeeping, not enforcement: an expired token is already refused on read, so
 * nothing turns on this running. What it buys is a table proportional to the
 * number of devices currently trusted rather than to every device ever trusted.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_tokens extends \core\task\scheduled_task {

    /**
     * Name shown in the scheduled task administration screen.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskpurgetokens', 'local_profilefields');
    }

    /**
     * Delete what has expired.
     *
     * @return void
     */
    public function execute(): void {
        $removed = rememberme::purge_expired();

        mtrace("local_profilefields: removed {$removed} expired remember-me token(s).");
    }
}
