<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;
use local_academy\cert\program_scope;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the student's enrol_programs allocation is marked complete.
 *
 * Program-scoped: the 2nd engine argument is a programid, not a courseid. Delegates to the programs
 * plugin's own completion result (allocation timecompleted), so it respects whatever completion
 * rules the admin configured for the program. Fails safe (false) when the plugin is absent or the
 * student has no allocation.
 *
 * Config: none.
 */
class program_completed_rule implements rule_interface {

    public function get_type(): string {
        return 'program_completed';
    }

    public function get_label(): string {
        return get_string('cert_rule_program_completed', 'local_academy');
    }

    public function get_config_schema(): array {
        return [];
    }

    public function evaluate(int $userid, int $programid, array $config): bool {
        return program_scope::is_program_completed($userid, $programid);
    }

    public function measure(int $userid, int $programid, array $config): array {
        return [
            'actual'   => program_scope::is_program_completed($userid, $programid) ? 1 : 0,
            'required' => 1,
            'unit'     => '',
            'label'    => $this->get_label(),
        ];
    }
}
