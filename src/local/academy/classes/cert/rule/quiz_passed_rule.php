<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the student's grade on a specific quiz meets that quiz's pass grade.
 *
 * Uses the gradebook ({@see grade_get_grades}) rather than the quiz plugin internals: the quiz's
 * grade item carries `gradepass`, and the student's `finalgrade` is compared against it. If no pass
 * grade is configured on the quiz (gradepass <= 0) the rule cannot be satisfied and returns false —
 * the teacher must set a pass grade for a "quiz passed" certificate rule.
 *
 * Config: ['quizid' => int]  (the quiz instance id, i.e. {quiz}.id)
 */
class quiz_passed_rule implements rule_interface {

    public function get_type(): string {
        return 'quiz_passed';
    }

    public function get_label(): string {
        return get_string('cert_rule_quiz_passed', 'local_academy');
    }

    public function get_config_schema(): array {
        return [[
            'name'    => 'quizid',
            'type'    => 'select_quiz',
            'label'   => get_string('cert_rule_quiz', 'local_academy'),
            'default' => 0,
        ]];
    }

    public function evaluate(int $userid, int $courseid, array $config): bool {
        list($grade, $gradepass) = $this->grade($userid, $courseid, (int)($config['quizid'] ?? 0));
        return $gradepass > 0 && $grade !== null && $grade >= $gradepass;
    }

    public function measure(int $userid, int $courseid, array $config): array {
        list($grade, $gradepass) = $this->grade($userid, $courseid, (int)($config['quizid'] ?? 0));
        return [
            'actual'   => $grade === null ? null : round($grade, 2),
            'required' => $gradepass > 0 ? round($gradepass, 2) : null,
            'unit'     => get_string('cert_unit_points', 'local_academy'),
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $quizid quiz instance id
     * @return array [?float finalgrade, float gradepass]
     */
    private function grade(int $userid, int $courseid, int $quizid): array {
        if ($quizid <= 0) {
            return [null, 0.0];
        }
        $grades = grade_get_grades($courseid, 'mod', 'quiz', $quizid, $userid);
        if (empty($grades->items[0])) {
            return [null, 0.0];
        }
        $item = $grades->items[0];
        $gradepass = (float)($item->gradepass ?? 0);
        $usergrade = isset($item->grades[$userid]) ? $item->grades[$userid]->grade : null;
        return [$usergrade === null ? null : (float)$usergrade, $gradepass];
    }
}
