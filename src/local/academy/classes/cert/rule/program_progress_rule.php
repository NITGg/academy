<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;
use local_academy\cert\program_scope;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when the share of a program's courses the student has completed is at least a threshold %.
 *
 * Program-scoped: the 2nd engine argument is a programid. Progress % = completed program courses ÷
 * total program courses × 100, per-course completion via core {@see \completion_info}. Mirrors
 * {@see course_progress_rule} at program level. Returns 0% when the program has no courses or the
 * programs plugin is absent.
 *
 * Config: ['threshold' => float 0..100]
 */
class program_progress_rule implements rule_interface {

    public function get_type(): string {
        return 'program_progress';
    }

    public function get_label(): string {
        return get_string('cert_rule_program_progress', 'local_academy');
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

    public function evaluate(int $userid, int $programid, array $config): bool {
        $threshold = (float)($config['threshold'] ?? 0);
        return $this->progress($userid, $programid) >= $threshold;
    }

    public function measure(int $userid, int $programid, array $config): array {
        return [
            'actual'   => round($this->progress($userid, $programid), 2),
            'required' => (float)($config['threshold'] ?? 0),
            'unit'     => '%',
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $programid
     * @return float 0..100 (0 when the program has no courses).
     */
    private function progress(int $userid, int $programid): float {
        $courseids = program_scope::course_ids($programid);
        $total = count($courseids);
        if ($total === 0) {
            return 0.0;
        }
        $done = program_scope::completed_course_count($userid, $courseids);
        return ($done / $total) * 100;
    }
}
