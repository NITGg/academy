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
 * Who a course lets its students write to.
 *
 * Stored as one integer per course. Negative means "no restriction at all"; zero or above is
 * a bitmask of the groups a student may still reach, so an administrator can allow any
 * combination - teachers only, admins only, teachers and admins, and so on - and an empty
 * mask (0) means the students on that course may message nobody.
 *
 * Teachers are never restricted by any of this: the setting is about what a *student* may do.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rules {

    /**
     * No restriction - students message whoever the site normally lets them.
     *
     * Deliberately negative rather than a fourth bit, because "unrestricted" is not one more
     * group you may reach; it is the absence of the whole scheme, and a mask of 0 has to stay
     * free to mean "nobody at all".
     */
    public const OPEN = -1;

    /** @var int Restricted, with nothing allowed: these students may message nobody. */
    public const ALLOW_NOBODY = 0;

    /** @var int May message the other students on the course. */
    public const ALLOW_PEERS = 1;

    /** @var int May message the teachers of the course. */
    public const ALLOW_TEACHERS = 2;

    /** @var int May message site administrators. */
    public const ALLOW_ADMINS = 4;

    /**
     * The three groups a restricted student can be allowed to reach.
     *
     * @return array [flag => label]
     */
    public static function get_flags(): array {
        return [
            self::ALLOW_TEACHERS => get_string('allowteachers', 'local_msgrules'),
            self::ALLOW_ADMINS   => get_string('allowadmins', 'local_msgrules'),
            self::ALLOW_PEERS    => get_string('allowpeers', 'local_msgrules'),
        ];
    }

    /**
     * Is this course outside the scheme entirely?
     *
     * @param int $mode
     * @return bool
     */
    public static function is_open(int $mode): bool {
        return $mode < 0;
    }

    /**
     * Does this mode let a student reach the given group?
     *
     * @param int $mode
     * @param int $flag One of the ALLOW_* constants.
     * @return bool
     */
    public static function allows(int $mode, int $flag): bool {
        return !self::is_open($mode) && ($mode & $flag) === $flag;
    }

    /**
     * A human sentence for one mode, for the management screen and the CLI.
     *
     * @param int $mode
     * @return string
     */
    public static function describe(int $mode): string {
        if (self::is_open($mode)) {
            return get_string('modeopen', 'local_msgrules');
        }

        $parts = [];
        foreach (self::get_flags() as $flag => $label) {
            if (self::allows($mode, $flag)) {
                $parts[] = $label;
            }
        }

        if (!$parts) {
            return get_string('modenobody', 'local_msgrules');
        }

        return get_string('modeallowlist', 'local_msgrules', implode(get_string('listsep', 'langconfig') . ' ', $parts));
    }

    /**
     * Is the plugin switched on?
     *
     * Off is the shipped default: installing must not cut a single conversation before an
     * administrator has chosen the settings and decided to turn them on.
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

        return $mode === false || $mode === '' ? self::OPEN : (int) $mode;
    }

    /**
     * Set the site-wide default.
     *
     * @param int $mode
     * @return void
     */
    public static function set_default_mode(int $mode): void {
        set_config('defaultmode', $mode, 'local_msgrules');
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

        if (!self::is_open(self::get_default_mode())) {
            // Every course without an override is restricted, so unless every single course
            // has been opened by hand there is something to do.
            return true;
        }

        return $DB->record_exists_select('local_msgrules_course', 'mode >= 0');
    }
}
