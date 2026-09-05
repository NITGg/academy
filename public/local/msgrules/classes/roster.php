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
 * Works out, from course enrolments and roles, who each restricted student may write to.
 *
 * Everything here is read in a handful of queries and then resolved in memory. Asking the
 * database per user would be the obvious way to write it and the wrong one: the answer is
 * needed for every student at once, and the inputs - enrolments and role assignments - are
 * small tables next to the number of pairs they generate.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roster {

    /**
     * Build the whole picture in one pass.
     *
     * @return array{
     *     eligible: array<int, bool>,
     *     restricted: array<int, bool>,
     *     allowed: array<int, array<int, bool>>
     * } eligible: every account the rules may touch. restricted: the students a mode applies
     * to. allowed: for each restricted student, the accounts they may still write to.
     */
    public static function build(): array {
        $eligible = self::get_eligible_users();

        $result = [
            'eligible'   => $eligible,
            'restricted' => [],
            'allowed'    => [],
        ];

        if (!rules::has_any_restriction()) {
            // Every course is open; nothing to derive.
            return $result;
        }

        $modes = self::get_effective_modes();
        $enrolments = self::get_enrolments($eligible);      // courseid => [userid => true]
        $teachers = self::get_teachers($eligible);          // courseid => [userid => true]

        // A user who teaches anywhere is never restricted. Without this, an instructor who is
        // also enrolled as a student on one restricted course would silently lose the ability
        // to write to their own students on every other course.
        $teachesanywhere = [];
        foreach ($teachers as $courseteachers) {
            $teachesanywhere += $courseteachers;
        }

        // Which students each restricted course covers, and every course those students are on
        // (open ones included - an open course still grants its participants).
        $courselist = [];                                   // userid => [courseid => true]
        foreach ($enrolments as $courseid => $participants) {
            foreach (array_keys($participants) as $userid) {
                $courselist[$userid][$courseid] = true;
            }
        }

        foreach ($enrolments as $courseid => $participants) {
            $mode = $modes[$courseid] ?? rules::get_default_mode();
            if ($mode === rules::MODE_OPEN) {
                continue;
            }
            foreach (array_keys($participants) as $userid) {
                if (isset($teachesanywhere[$userid]) || isset($teachers[$courseid][$userid])) {
                    continue;                               // Teachers are not restricted.
                }
                $result['restricted'][$userid] = true;
            }
        }

        // For every restricted student, gather what each of their courses still permits. A
        // student on two courses gets the union: a restriction on one course is not a reason
        // to cut them off from another course that was left open.
        foreach (array_keys($result['restricted']) as $userid) {
            $allowed = [];
            foreach (array_keys($courselist[$userid] ?? []) as $courseid) {
                $mode = $modes[$courseid] ?? rules::get_default_mode();
                $courseteachers = $teachers[$courseid] ?? [];
                $participants = $enrolments[$courseid] ?? [];

                switch ($mode) {
                    case rules::MODE_PEERS:
                        // Fellow students: everyone on the course who is not teaching it.
                        $allowed += array_diff_key($participants, $courseteachers);
                        break;

                    case rules::MODE_TEACHERS_ONLY:
                        $allowed += $courseteachers;
                        break;

                    case rules::MODE_PEERS_AND_TEACHERS:
                    case rules::MODE_OPEN:
                    default:
                        $allowed += $participants;
                        break;
                }
            }
            unset($allowed[$userid]);                       // Self-conversations are core's.
            $result['allowed'][$userid] = $allowed;
        }

        return $result;
    }

    /**
     * Every account the rules may touch.
     *
     * Site administrators are the one exemption, so they are never blocked and never blocked
     * from - there is always a way to reach support. Deleted accounts and the guest are out
     * because neither holds a conversation.
     *
     * @return array<int, bool>
     */
    public static function get_eligible_users(): array {
        global $DB, $CFG;

        $exclude = array_map('intval', explode(',', (string) $CFG->siteadmins));
        $exclude[] = (int) $CFG->siteguest;
        $exclude = array_values(array_unique(array_filter($exclude)));

        [$notin, $params] = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'ex', false);

        $out = [];
        foreach ($DB->get_fieldset_select('user', 'id', "deleted = 0 AND id $notin", $params) as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    /**
     * The mode in force for every course on the site.
     *
     * @return array<int, int> courseid => mode
     */
    private static function get_effective_modes(): array {
        global $DB, $SITE;

        $default = rules::get_default_mode();
        $overrides = rules::get_course_modes();

        $modes = [];
        foreach ($DB->get_fieldset_select('course', 'id', 'id <> ?', [$SITE->id]) as $courseid) {
            $courseid = (int) $courseid;
            $modes[$courseid] = $overrides[$courseid] ?? $default;
        }

        return $modes;
    }

    /**
     * Active enrolments, as courseid => [userid => true].
     *
     * Only active ones: a suspended enrolment does not put somebody in a class, and counting it
     * would keep a student tied to a course they have left.
     *
     * @param array<int, bool> $eligible
     * @return array<int, array<int, bool>>
     */
    private static function get_enrolments(array $eligible): array {
        global $DB;

        $sql = "SELECT ue.id, ue.userid, e.courseid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.status = 0 AND e.status = 0";

        $out = [];
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $userid = (int) $row->userid;
            if (isset($eligible[$userid])) {
                $out[(int) $row->courseid][$userid] = true;
            }
        }
        $rs->close();

        return $out;
    }

    /**
     * Who teaches each course, as courseid => [userid => true].
     *
     * Read by role archetype rather than by role name, so a site that renamed or cloned the
     * teacher roles still resolves correctly. Assignments made above the course - on its
     * category, or site-wide - count too, which is why the context path is walked instead of
     * only matching the course's own context.
     *
     * @param array<int, bool> $eligible
     * @return array<int, array<int, bool>>
     */
    private static function get_teachers(array $eligible): array {
        global $DB;

        // Every teacher-ish assignment, keyed by the context it was made in.
        $sql = "SELECT ra.id, ra.userid, ra.contextid
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE r.archetype IN ('editingteacher', 'teacher')";

        $bycontext = [];
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $userid = (int) $row->userid;
            if (isset($eligible[$userid])) {
                $bycontext[(int) $row->contextid][$userid] = true;
            }
        }
        $rs->close();

        if (!$bycontext) {
            return [];
        }

        // Resolve each course against its own context and every ancestor of it. ctx.path is
        // "/1/3/57", so the ids in it are exactly the contexts an assignment could sit in and
        // still apply here.
        $sql = "SELECT c.id, ctx.path
                  FROM {course} c
                  JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :level";

        $out = [];
        $rs = $DB->get_recordset_sql($sql, ['level' => CONTEXT_COURSE]);
        foreach ($rs as $row) {
            $teachers = [];
            foreach (explode('/', trim((string) $row->path, '/')) as $ctxid) {
                if ($ctxid !== '' && isset($bycontext[(int) $ctxid])) {
                    $teachers += $bycontext[(int) $ctxid];
                }
            }
            if ($teachers) {
                $out[(int) $row->id] = $teachers;
            }
        }
        $rs->close();

        return $out;
    }
}
