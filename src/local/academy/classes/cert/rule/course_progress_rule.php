<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the student's course completion progress is at least a threshold percentage.
 *
 * Progress % comes from core completion ({@see \core_completion\progress}) — the share of
 * completion-tracked activities the student has completed. Returns 0% when completion is not
 * enabled/tracked for the course/user.
 *
 * Config: ['threshold' => float 0..100]
 */
class course_progress_rule implements rule_interface {

    public function get_type(): string {
        return 'course_progress';
    }

    public function get_label(): string {
        return get_string('cert_rule_course_progress', 'local_academy');
    }

    public function get_config_schema(): array {
        return [[
            'name'    => 'threshold',
            'type'    => 'number',
            'label'   => get_string('cert_rule_threshold_percent', 'local_academy'),
            'default' => 90,
            'min'     => 0,
            'max'     => 100,
        ]];
    }

    public function evaluate(int $userid, int $courseid, array $config): bool {
        $threshold = (float)($config['threshold'] ?? 0);
        return $this->progress($userid, $courseid) >= $threshold;
    }

    public function measure(int $userid, int $courseid, array $config): array {
        return [
            'actual'   => round($this->progress($userid, $courseid), 2),
            'required' => (float)($config['threshold'] ?? 0),
            'unit'     => '%',
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return float 0..100 (0 when completion is not enabled/tracked).
     */
    private function progress(int $userid, int $courseid): float {
        global $DB;
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $pct = \core_completion\progress::get_course_progress_percentage($course, $userid);
        return $pct === null ? 0.0 : (float)$pct;
    }
}
