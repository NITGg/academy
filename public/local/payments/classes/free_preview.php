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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Which activities a visitor may open before enrolling — the "free preview" flag (AC-4.9.5).
 *
 * {@see course_preview} already lets anyone read /course/view.php with every activity
 * locked. This class is the exception list on top of it: an activity a teacher has ticked
 * as a free preview stays open, so a shopper can watch one lesson before buying, while
 * every other activity keeps showing the padlock and the "enrol to unlock" prompt.
 *
 * Moodle has no field for this. The nearest core mechanisms are enrol_guest (all or
 * nothing — it opens the whole course) and the availability API (which expresses
 * restrictions, not exemptions, and has no "is not enrolled" condition), so the flag is
 * ours: one row per flagged course module, written from the activity settings form
 * (see local_payments_coursemodule_standard_elements() in lib.php).
 *
 * Reads happen on every request that touches a course a visitor cannot enter, so a course's
 * whole set is fetched in one query and cached per request.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class free_preview {

    /** @var string the table holding one row per flagged course module. */
    public const TABLE = 'local_payments_free_preview';

    /** @var string name of the checkbox added to every activity settings form. */
    public const FORMFIELD = 'localpaymentsfreepreview';

    /** @var array<int, array<int, bool>> courseid => [cmid => true], filled on first read. */
    protected static array $bycourse = [];

    /** @var array<int, bool> cmid => flag, for lookups made without a course id. */
    protected static array $bycmid = [];

    /**
     * Every activity flagged as a free preview in this course, as cmid => true.
     *
     * @param int $courseid
     * @return array<int, bool>
     */
    public static function for_course(int $courseid): array {
        global $DB;

        if ($courseid <= 0) {
            return [];
        }
        if (isset(self::$bycourse[$courseid])) {
            return self::$bycourse[$courseid];
        }

        $cmids = $DB->get_fieldset_select(self::TABLE, 'cmid', 'courseid = ?', [$courseid]);
        $set = [];
        foreach ($cmids as $cmid) {
            $set[(int) $cmid] = true;
            self::$bycmid[(int) $cmid] = true;
        }

        return self::$bycourse[$courseid] = $set;
    }

    /**
     * Is this activity playable by a visitor who has not enrolled?
     *
     * Pass the course id when it is already known (a course page has it) so the answer comes
     * from the set already loaded rather than a query of its own.
     *
     * @param int $cmid course_modules.id
     * @param int $courseid optional, when the caller already knows it
     * @return bool
     */
    public static function is_free(int $cmid, int $courseid = 0): bool {
        global $DB;

        if ($cmid <= 0) {
            return false;
        }
        if ($courseid > 0) {
            return !empty(self::for_course($courseid)[$cmid]);
        }
        if (isset(self::$bycmid[$cmid])) {
            return self::$bycmid[$cmid];
        }

        return self::$bycmid[$cmid] = $DB->record_exists(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Turn the flag on or off for one activity.
     *
     * @param int $cmid course_modules.id
     * @param int $courseid the course that module belongs to
     * @param bool $free
     * @return void
     */
    public static function set(int $cmid, int $courseid, bool $free): void {
        global $DB;

        if ($cmid <= 0 || $courseid <= 0) {
            return;
        }

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid], '*', IGNORE_MISSING);

        if (!$free) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
            }
        } else if (!$existing) {
            $DB->insert_record(self::TABLE, (object) [
                'cmid'         => $cmid,
                'courseid'     => $courseid,
                'timemodified' => time(),
            ]);
        } else if ((int) $existing->courseid !== $courseid) {
            // The activity was moved to another course (restore, duplicate) — keep the row honest.
            $DB->set_field(self::TABLE, 'courseid', $courseid, ['id' => $existing->id]);
        }

        unset(self::$bycourse[$courseid], self::$bycmid[$cmid]);
    }

    /**
     * Drop the flag of a deleted activity, so a later module reusing the id is not born free.
     *
     * @param int $cmid course_modules.id
     * @return void
     */
    public static function forget(int $cmid): void {
        global $DB;

        if ($cmid <= 0) {
            return;
        }
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
        self::$bycourse = [];
        unset(self::$bycmid[$cmid]);
    }
}
