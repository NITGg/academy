<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only helpers the program-scoped rules share to inspect an enrol_programs program.
 *
 * Kept separate from any certificate concept (rules must not know about certificates) and from the
 * third-party plugin's own classes, so a plugin update cannot break it: everything here is plain
 * queries against the plugin's tables, guarded by {@see available()}. When the programs plugin is
 * absent every method degrades to "empty / not completed" so the rules fail safe.
 */
class program_scope {

    /** Is the enrol_programs plugin installed and its tables present? */
    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('enrol_programs_programs');
    }

    /**
     * The course ids that make up a program (its course items — course-sets and the top item have
     * no courseid and are skipped).
     *
     * @param int $programid
     * @return int[] distinct course ids (may be empty)
     */
    public static function course_ids(int $programid): array {
        global $DB;
        if (!self::available()) {
            return [];
        }
        $records = $DB->get_records_select(
            'enrol_programs_items',
            'programid = :programid AND courseid IS NOT NULL',
            ['programid' => $programid],
            '',
            'DISTINCT courseid'
        );
        return array_map('intval', array_keys($records));
    }

    /**
     * Has the student's program allocation been marked complete? Uses the plugin's own completion
     * result (enrol_programs_allocations.timecompleted), which reflects whatever completion rules
     * the admin configured for the program.
     *
     * @param int $userid
     * @param int $programid
     * @return bool
     */
    public static function is_program_completed(int $userid, int $programid): bool {
        global $DB;
        if (!self::available()) {
            return false;
        }
        $alloc = $DB->get_record('enrol_programs_allocations',
            ['programid' => $programid, 'userid' => $userid, 'archived' => 0], 'id, timecompleted');
        return $alloc && !empty($alloc->timecompleted);
    }

    /**
     * How many of a program's courses the student has individually completed.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return int count of complete courses
     */
    public static function completed_course_count(int $userid, array $courseids): int {
        global $DB;
        $done = 0;
        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }
            $completion = new \completion_info($course);
            if ($completion->is_enabled() && $completion->is_course_complete($userid)) {
                $done++;
            }
        }
        return $done;
    }
}
