<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the course is marked complete for the student.
 *
 * Uses core course completion ({@see \completion_info::is_course_complete}). The course must have
 * completion enabled and completion criteria defined for this to pass.
 *
 * Config: none.
 */
class course_completed_rule implements rule_interface {

    public function get_type(): string {
        return 'course_completed';
    }

    public function get_label(): string {
        return get_string('cert_rule_course_completed', 'local_academy');
    }

    public function get_config_schema(): array {
        return [];
    }

    public function evaluate(int $userid, int $courseid, array $config): bool {
        return $this->complete($userid, $courseid);
    }

    public function measure(int $userid, int $courseid, array $config): array {
        return [
            'actual'   => $this->complete($userid, $courseid) ? 1 : 0,
            'required' => 1,
            'unit'     => '',
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    private function complete(int $userid, int $courseid): bool {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return false;
        }
        return (bool)$completion->is_course_complete($userid);
    }
}
