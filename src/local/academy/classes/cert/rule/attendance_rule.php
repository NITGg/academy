<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the student's live-session attendance is at least a threshold percentage.
 *
 * Attendance is Academy-specific (there is no mod_attendance): it is tracked by local_academysessions
 * in {academy_session_attendance}. We count only sessions the student was assigned to
 * ({academy_session_students}) whose session has finished ({academy_live_sessions}.status = 'completed'):
 *
 *   attendance% = attended completed sessions / expected completed sessions * 100
 *
 * Returns 0% when the student had no completed sessions to attend.
 *
 * Config: ['threshold' => float 0..100]
 */
class attendance_rule implements rule_interface {

    public function get_type(): string {
        return 'attendance';
    }

    public function get_label(): string {
        return get_string('cert_rule_attendance', 'local_academy');
    }

    public function get_config_schema(): array {
        return [[
            'name'    => 'threshold',
            'type'    => 'number',
            'label'   => get_string('cert_rule_threshold_percent', 'local_academy'),
            'default' => 70,
            'min'     => 0,
            'max'     => 100,
        ]];
    }

    public function describe(int $courseid, array $config): string {
        return get_string('cert_req_attendance', 'local_academy',
            format_float((float)($config['threshold'] ?? 0), 0));
    }

    public function evaluate(int $userid, int $courseid, array $config): bool {
        $threshold = (float)($config['threshold'] ?? 0);
        return $this->attendance($userid, $courseid) >= $threshold;
    }

    public function measure(int $userid, int $courseid, array $config): array {
        return [
            'actual'   => round($this->attendance($userid, $courseid), 2),
            'required' => (float)($config['threshold'] ?? 0),
            'unit'     => '%',
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return float 0..100 (0 when there were no completed sessions to attend).
     */
    private function attendance(int $userid, int $courseid): float {
        global $DB;

        // Sessions the student was expected to attend (assigned + completed).
        $expected = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {academy_live_sessions} s
                  JOIN {academy_session_students} ss ON ss.sessionid = s.id AND ss.userid = :suid
                 WHERE s.courseid = :courseid AND s.status = :status",
                ['suid' => $userid, 'courseid' => $courseid, 'status' => 'completed']);

        if ($expected == 0) {
            return 0.0;
        }

        // Of those, the ones the student actually joined.
        $attended = $DB->count_records_sql("
                SELECT COUNT(1)
                  FROM {academy_live_sessions} s
                  JOIN {academy_session_students} ss ON ss.sessionid = s.id AND ss.userid = :suid
                  JOIN {academy_session_attendance} a ON a.sessionid = s.id AND a.userid = :auid
                 WHERE s.courseid = :courseid AND s.status = :status",
                ['suid' => $userid, 'auid' => $userid, 'courseid' => $courseid, 'status' => 'completed']);

        return ($attended / $expected) * 100;
    }
}
