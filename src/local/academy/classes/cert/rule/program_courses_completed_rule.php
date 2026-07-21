<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;
use local_academy\cert\program_scope;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when EVERY course in the program is individually complete for the student.
 *
 * Program-scoped: the 2nd engine argument is a programid. Unlike {@see program_completed_rule}
 * (which trusts the program's own completion logic), this checks each course's core completion
 * directly, so it stays true even if the program's sequencing/prerequisite rules would not mark the
 * whole program complete. Fails safe (false) when the program has no courses or the plugin is
 * absent.
 *
 * Config: none.
 */
class program_courses_completed_rule implements rule_interface {

    public function get_type(): string {
        return 'program_courses_completed';
    }

    public function get_label(): string {
        return get_string('cert_rule_program_courses_completed', 'local_academy');
    }

    public function get_config_schema(): array {
        return [];
    }

    public function evaluate(int $userid, int $programid, array $config): bool {
        $courseids = program_scope::course_ids($programid);
        $total = count($courseids);
        if ($total === 0) {
            return false;
        }
        return program_scope::completed_course_count($userid, $courseids) === $total;
    }

    public function measure(int $userid, int $programid, array $config): array {
        $courseids = program_scope::course_ids($programid);
        $total = count($courseids);
        return [
            'actual'   => program_scope::completed_course_count($userid, $courseids),
            'required' => $total,
            'unit'     => '',
            'label'    => $this->get_label(),
        ];
    }
}
