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

namespace local_payments\event;

defined('MOODLE_INTERNAL') || die();

use local_payments\free_preview;

/**
 * Core event observers for local_payments.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * An activity was deleted: drop its free-preview flag.
     *
     * Course module ids are reused, so a row left behind would eventually hand a brand new
     * activity the "anyone may play this" flag without anybody choosing it.
     *
     * @param \core\event\course_module_deleted $event
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        free_preview::forget((int) $event->objectid);
    }

    /**
     * A course was deleted: drop the free-preview flags of everything that was in it.
     *
     * course_module_deleted does fire for each activity of a deleted course, but only on the
     * paths that delete them one by one; this makes the cleanup unconditional.
     *
     * @param \core\event\course_deleted $event
     * @return void
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records(free_preview::TABLE, ['courseid' => (int) $event->objectid]);
    }
}
