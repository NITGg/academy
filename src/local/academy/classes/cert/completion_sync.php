<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * On-demand completion recalculation, so certificate eligibility never waits for cron.
 *
 * WHY THIS EXISTS
 * ---------------
 * Moodle only turns "the student met the criteria" into "the course is complete" inside the
 * scheduled task \core\task\completion_regular_task, and enrol_programs only turns "the courses are
 * complete" into "the program is complete" inside its own cron. Both write the flags our rules read
 * ({course_completions}.timecompleted and {enrol_programs_allocations}.timecompleted). If site cron
 * is stopped or lagging, a student can finish everything and still be reported "not eligible"
 * indefinitely — the rules are correct, the underlying flags are simply stale.
 *
 * This class performs exactly the same work as those two crons, but scoped to ONE user (and only the
 * courses that matter), cheaply enough to run inline while evaluating eligibility. It is a catch-up
 * mechanism, not a replacement: cron remains the right way to keep the whole site fresh, and nothing
 * here changes completion semantics — an aggregation that the cron would not have marked complete is
 * not marked complete here either.
 *
 * Everything is best-effort and fails silently: a certificate page must still render (showing
 * whatever the stored flags say) if recalculation is impossible.
 */
class completion_sync {

    /** @var array<string,bool> scopes already synced during this request, to avoid repeat work. */
    private static $synced = [];

    /**
     * Bring a program's completion state up to date for one student.
     *
     * Recalculates each of the program's courses first (a program can only complete once its courses
     * have), then asks enrol_programs to re-derive the allocation's completion.
     *
     * @param int $userid
     * @param int $programid
     */
    public static function sync_program(int $userid, int $programid): void {
        if ($userid <= 0 || $programid <= 0 || self::already('p', $userid, $programid)) {
            return;
        }

        foreach (program_scope::course_ids($programid) as $courseid) {
            self::sync_course($userid, $courseid);
        }

        if (!program_scope::available()) {
            return;
        }
        try {
            // Re-derives item completions and, when the top item is satisfied, stamps
            // {enrol_programs_allocations}.timecompleted — the flag program_completed_rule reads.
            \enrol_programs\local\allocation::fix_user_enrolments($programid, $userid);
        } catch (\Throwable $e) {
            debugging('local_academy: program completion sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Bring a course's completion state up to date for one student.
     *
     * Mirrors \core\task\completion_regular_task for a single user: review every criterion (which
     * records {course_completion_crit_compl}), then aggregate those criteria with the course's own
     * aggregation methods and, if the course is satisfied, mark it complete.
     *
     * @param int $userid
     * @param int $courseid
     */
    public static function sync_course(int $userid, int $courseid): void {
        global $CFG, $DB;

        if ($userid <= 0 || $courseid <= 0 || self::already('c', $userid, $courseid)) {
            return;
        }
        if (empty($CFG->enablecompletion)) {
            return;
        }
        require_once($CFG->libdir . '/completionlib.php');

        try {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                return;
            }
            $info = new \completion_info($course);
            if (!$info->is_enabled()) {
                return;
            }
            // Already complete: the flag our rules read is current, nothing to recalculate.
            if ($info->is_course_complete($userid)) {
                return;
            }

            $completions = $info->get_completions($userid);
            if (!$completions) {
                // A course with no criteria can never complete; say so rather than looking broken.
                return;
            }

            // Step 1 — record any criterion the student has met but that no cron has stored yet.
            foreach ($completions as $completion) {
                if ($completion->is_complete()) {
                    continue;
                }
                try {
                    $completion->get_criteria()->review($completion);
                } catch (\Throwable $e) {
                    // A broken criterion (e.g. deleted activity) must not stop the others.
                    debugging('local_academy: criterion review failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            // Step 2 — aggregate, exactly as completion_regular_task does. Re-read the completions so
            // the statuses reflect whatever step 1 just recorded.
            $overall      = $info->get_aggregation_method();
            $activity     = $info->get_aggregation_method(COMPLETION_CRITERIA_TYPE_ACTIVITY);
            $prerequisite = $info->get_aggregation_method(COMPLETION_CRITERIA_TYPE_COURSE);
            $role         = $info->get_aggregation_method(COMPLETION_CRITERIA_TYPE_ROLE);

            $overallstatus      = null;
            $activitystatus     = null;
            $prerequisitestatus = null;
            $rolestatus         = null;
            $timecompleted      = null;

            foreach ($info->get_completions($userid) as $completion) {
                $iscomplete = $completion->is_complete();
                if ($iscomplete) {
                    $timecompleted = max($timecompleted, (int)$completion->timecompleted);
                }
                $type = (int)$completion->get_criteria()->criteriatype;
                if ($type == COMPLETION_CRITERIA_TYPE_ACTIVITY) {
                    completion_cron_aggregate($activity, $iscomplete, $activitystatus);
                } else if ($type == COMPLETION_CRITERIA_TYPE_COURSE) {
                    completion_cron_aggregate($prerequisite, $iscomplete, $prerequisitestatus);
                } else if ($type == COMPLETION_CRITERIA_TYPE_ROLE) {
                    completion_cron_aggregate($role, $iscomplete, $rolestatus);
                } else {
                    completion_cron_aggregate($overall, $iscomplete, $overallstatus);
                }
            }

            // Fold the per-type results into the overall one, in the core task's order.
            if ($rolestatus !== null) {
                completion_cron_aggregate($overall, $rolestatus, $overallstatus);
            }
            if ($activitystatus !== null) {
                completion_cron_aggregate($overall, $activitystatus, $overallstatus);
            }
            if ($prerequisitestatus !== null) {
                completion_cron_aggregate($overall, $prerequisitestatus, $overallstatus);
            }

            // Step 3 — stamp {course_completions}.timecompleted, the flag the course rules read.
            if ($overallstatus) {
                $ccompletion = new \completion_completion(['course' => $courseid, 'userid' => $userid]);
                $ccompletion->mark_complete($timecompleted ?: null);
            }
        } catch (\Throwable $e) {
            debugging('local_academy: course completion sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Has this scope already been synced in this request? Marks it as synced on first ask, so the
     * repeated eligibility calls a page makes (one per certificate) do the work only once.
     *
     * @param string $kind 'c' course or 'p' program
     * @param int $userid
     * @param int $scopeid
     * @return bool true when a previous call already handled it
     */
    private static function already(string $kind, int $userid, int $scopeid): bool {
        $key = $kind . ':' . $userid . ':' . $scopeid;
        if (isset(self::$synced[$key])) {
            return true;
        }
        self::$synced[$key] = true;
        return false;
    }
}
