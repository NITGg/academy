<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class enrollment_handler {

    /**
     * Enrol a user into a course using manual enrolment.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $roleid Role to assign. Default: student (5).
     * @return bool
     */
    public static function enrol_user(int $userid, int $courseid, int $roleid = 5): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $enrol = enrol_get_plugin('manual');
        if (!$enrol) {
            throw new \moodle_exception('enrolpluginnotinstalled', 'local_payments', '', 'manual');
        }

        // Find the manual enrolment instance for this course.
        $instances = enrol_get_instances($courseid, true);
        $manual_instance = null;
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manual_instance = $instance;
                break;
            }
        }

        // Create manual instance if it doesn't exist.
        if (!$manual_instance) {
            $enrolid = $enrol->add_instance($course);
            $manual_instance = $DB->get_record('enrol', ['id' => $enrolid], '*', MUST_EXIST);
        }

        // Check if already actively enrolled. An expired/suspended enrolment
        // must fall through so we re-enrol (manual enrol_user updates the
        // existing record's timestart/timeend), restoring access.
        if (is_enrolled(\context_course::instance($courseid), $userid, '', true)) {
            return true;
        }

        $enrol->enrol_user($manual_instance, $userid, $roleid, time(), 0);

        return is_enrolled(\context_course::instance($courseid), $userid, '', true);
    }

    /**
     * Check if a user has an *active* enrolment in a course.
     *
     * Uses $onlyactive = true so that expired enrolments (past their timeend,
     * e.g. a lapsed subscription/package) or suspended ones are treated as
     * not-enrolled — matching what actual course access allows. Otherwise the
     * UI would keep showing "Enrolled" while the course itself denies entry.
     */
    public static function is_enrolled(int $userid, int $courseid): bool {
        return is_enrolled(\context_course::instance($courseid), $userid, '', true);
    }

    /**
     * Classify *why* a user is not actively enrolled in a course, so course cards
     * can show the right hint instead of always assuming a lapsed subscription.
     *
     * Core is_enrolled() lumps every enrolment method together and can only say
     * "enrolled but not active", which is true for two very different situations:
     *   - a lapsed subscription/package (our own manual enrolment, given a real
     *     timeend by local_academy, whose end date has now passed) — the student
     *     should be invited to renew; and
     *   - a scheduled/future start (e.g. an enrol_programs allocation with a start
     *     date set in program_allocation.php) — the student registered and is
     *     simply waiting for access to begin, which must NOT look like a renewal.
     *
     * Returns ['state' => 'active'|'scheduled'|'expired'|'none', 'starts_at' => int]
     * where starts_at is the earliest known future start timestamp (0 if unknown).
     */
    public static function enrolment_state(int $userid, int $courseid): array {
        global $DB;

        $context = \context_course::instance($courseid);

        if (is_enrolled($context, $userid, '', true)) {
            return ['state' => 'active', 'starts_at' => 0];
        }
        if (!is_enrolled($context, $userid, '', false)) {
            return ['state' => 'none', 'starts_at' => 0];
        }

        // Enrolled at all but not active — inspect the raw records to tell an
        // upcoming/scheduled allocation apart from a genuinely lapsed one.
        $records = $DB->get_records_sql(
            "SELECT ue.id, ue.status, ue.timestart, ue.timeend, e.enrol, e.status AS instancestatus
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        $now = time();
        $scheduledstart = 0;   // earliest future start we find
        $hasexpired = false;   // a genuinely lapsed (past timeend) enrolment
        $hasprogram = false;   // a program enrolment that is otherwise inactive

        foreach ($records as $r) {
            $timestart = (int) $r->timestart;
            $timeend   = (int) $r->timeend;
            $active    = ((int) $r->status === ENROL_USER_ACTIVE)
                      && ((int) $r->instancestatus === ENROL_INSTANCE_ENABLED);

            // Access that hasn't begun yet — a scheduled/future start (a program
            // allocation with a start date). Registered, waiting to start.
            if ($active && $timestart > $now) {
                if ($scheduledstart === 0 || $timestart < $scheduledstart) {
                    $scheduledstart = $timestart;
                }
                continue;
            }
            // Access that ran out — a past end date. This is the lapsed
            // subscription/package case our own enrolments produce.
            if ($timeend > 0 && $timeend <= $now) {
                $hasexpired = true;
                continue;
            }
            // A program enrolment inactive for some other reason (e.g. suspended
            // until its scheduled start) — treat as upcoming, never as a lapse.
            if ($r->enrol === 'programs') {
                $hasprogram = true;
            }
        }

        // A pending/upcoming start wins over everything: the student has access
        // coming, so "renew" would be misleading.
        if ($scheduledstart > 0 || $hasprogram) {
            return ['state' => 'scheduled', 'starts_at' => $scheduledstart];
        }
        if ($hasexpired) {
            return ['state' => 'expired', 'starts_at' => 0];
        }
        // Enrolled-but-inactive for some other reason (e.g. an admin-suspended
        // manual enrolment) — preserve the original "renew" hint.
        return ['state' => 'expired', 'starts_at' => 0];
    }

    /**
     * Whether the user has an enrolment record in the course that is no longer
     * active because it *lapsed* — a subscription/package whose access has ended.
     * Used to surface a "Renew your subscription" hint on course cards that keep
     * showing in "My courses" after access expired. A scheduled/not-yet-started
     * enrolment (e.g. a program with a future start date) is deliberately excluded
     * — see enrolment_state().
     */
    public static function has_expired_enrolment(int $userid, int $courseid): bool {
        return self::enrolment_state($userid, $courseid)['state'] === 'expired';
    }

    /**
     * Unenrol a user from a course (for refund scenarios).
     */
    public static function unenrol_user(int $userid, int $courseid): bool {
        $enrol = enrol_get_plugin('manual');
        if (!$enrol) {
            return false;
        }

        $instances = enrol_get_instances($courseid, true);
        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $enrol->unenrol_user($instance, $userid);
                return true;
            }
        }
        return false;
    }
}
