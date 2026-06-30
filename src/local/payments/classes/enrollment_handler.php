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

        // Check if already enrolled.
        if (is_enrolled(\context_course::instance($courseid), $userid)) {
            return true;
        }

        $enrol->enrol_user($manual_instance, $userid, $roleid, time(), 0);

        return is_enrolled(\context_course::instance($courseid), $userid);
    }

    /**
     * Check if a user is enrolled in a course.
     */
    public static function is_enrolled(int $userid, int $courseid): bool {
        return is_enrolled(\context_course::instance($courseid), $userid);
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
