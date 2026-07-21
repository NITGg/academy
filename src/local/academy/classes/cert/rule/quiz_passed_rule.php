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

    public function describe(int $courseid, array $config): string {
        global $DB;

        $quizid = (int)($config['quizid'] ?? 0);
        $name = $quizid > 0 ? $DB->get_field('quiz', 'name', ['id' => $quizid], IGNORE_MISSING) : false;
        if ($name === false || trim((string)$name) === '') {
            return ''; // Quiz deleted or never picked — 'Pass the quiz ""' helps nobody; use the label.
        }
        $a = new \stdClass();
        $a->quiz = format_string($name);

        // The pass mark lives on the grade item, not the quiz — and it is the number the student
        // actually has to beat, so name it when it is set. Passing 0 for userid: we want the item's
        // gradepass, not anyone's grade.
        list(, $gradepass) = $this->grade(0, $courseid, $quizid);
        if ($gradepass > 0) {
            $a->grade = format_float($gradepass, 2, true, true);
            return get_string('cert_req_quiz_passed_grade', 'local_academy', $a);
        }
        return get_string('cert_req_quiz_passed', 'local_academy', $a->quiz);
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
        global $CFG;
        if ($quizid <= 0) {
            return [null, 0.0];
        }
        // Not autoloaded — a web page usually has it already, but CLI (scheduled tasks) and the
        // student card do not, and the call would fatal with "undefined function".
        require_once($CFG->libdir . '/gradelib.php');
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
