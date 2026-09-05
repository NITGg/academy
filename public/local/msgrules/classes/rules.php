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

namespace local_msgrules;

/**
 * What a course allows its students to do in messaging.
 *
 * One mode per course, plus a site-wide default for every course without its own. Teachers are
 * never restricted by this - the rule is about who a *student* may write to.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {

    /** @var int No restriction - students message whoever the site normally lets them. */
    public const MODE_OPEN = 0;

    /** @var int Students may write to the other students on the course, and nobody else. */
    public const MODE_PEERS = 1;

    /** @var int Students may write to the other students on the course and to its teachers. */
    public const MODE_PEERS_AND_TEACHERS = 2;

    /** @var int Students may write to the course teachers only, not to each other. */
    public const MODE_TEACHERS_ONLY = 3;

    /**
     * The four modes, for a dropdown.
     *
     * @return array [mode => label]
     */
    public static function get_modes(): array {
        return [
            self::MODE_OPEN               => get_string('modeopen', 'local_msgrules'),
            self::MODE_PEERS              => get_string('modepeers', 'local_msgrules'),
            self::MODE_PEERS_AND_TEACHERS => get_string('modepeersteachers', 'local_msgrules'),
            self::MODE_TEACHERS_ONLY      => get_string('modeteachers', 'local_msgrules'),
        ];
    }

    /**
     * Is the plugin switched on?
     *
     * Off is the shipped default: installing must not cut a single conversation before an
     * administrator has chosen the modes and decided to turn it on.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_msgrules', 'enabled'));
    }

    /**
     * The mode used by every course that has not been given one of its own.
     *
     * @return int
     */
    public static function get_default_mode(): int {
        $mode = get_config('local_msgrules', 'defaultmode');

        return $mode === false ? self::MODE_OPEN : (int) $mode;
    }

    /**
     * The ceiling on how many accounts a rebuild will process.
     *
     * A restricted student needs one block row per person on the site they may not write to,
     * so the work grows with the roster. The guard turns "the site quietly got slow" into a
     * refusal with a number in it.
     *
     * @return int
     */
    public static function get_max_users(): int {
        return (int) (get_config('local_msgrules', 'maxusers') ?: 2000);
    }

    /**
     * The per-course overrides, as courseid => mode.
     *
     * Only courses that differ from the site default are stored, so this stays small however
     * many courses the site has.
     *
     * @return array
     */
    public static function get_course_modes(): array {
        global $DB;

        $out = [];
        foreach ($DB->get_records('local_msgrules_course') as $row) {
            $out[(int) $row->courseid] = (int) $row->mode;
        }
        return $out;
    }

    /**
     * The mode actually in force for one course.
     *
     * @param int $courseid
     * @return int
     */
    public static function get_course_mode(int $courseid): int {
        $overrides = self::get_course_modes();

        return $overrides[$courseid] ?? self::get_default_mode();
    }

    /**
     * Give one course its own mode, or hand it back to the site default.
     *
     * @param int $courseid
     * @param int|null $mode Null clears the override.
     * @return void
     */
    public static function set_course_mode(int $courseid, ?int $mode): void {
        global $DB;

        $existing = $DB->get_record('local_msgrules_course', ['courseid' => $courseid]);

        if ($mode === null) {
            if ($existing) {
                $DB->delete_records('local_msgrules_course', ['id' => $existing->id]);
            }
            return;
        }

        if ($existing) {
            if ((int) $existing->mode !== $mode) {
                $DB->update_record('local_msgrules_course', (object) [
                    'id'           => $existing->id,
                    'mode'         => $mode,
                    'timemodified' => time(),
                ]);
            }
            return;
        }

        $DB->insert_record('local_msgrules_course', (object) [
            'courseid'     => $courseid,
            'mode'         => $mode,
            'timemodified' => time(),
        ]);
    }

    /**
     * Does any course on the site restrict its students?
     *
     * Answers "is there any work to do at all" without walking the roster, which is the common
     * case on a site that has the plugin installed but every course left open.
     *
     * @return bool
     */
    public static function has_any_restriction(): bool {
        global $DB;

        if (self::get_default_mode() !== self::MODE_OPEN) {
            // Every course without an override is restricted, so unless every single course
            // has been opened by hand there is something to do.
            return true;
        }

        return $DB->record_exists_select('local_msgrules_course', 'mode <> ?', [self::MODE_OPEN]);
    }
}
