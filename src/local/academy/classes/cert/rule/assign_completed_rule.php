<?php
namespace local_academy\cert\rule;

use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * Passes when a specific assignment activity is marked complete for the student.
 *
 * Uses Moodle activity completion ({@see \completion_info::get_data}); "completed" means any
 * completion state other than incomplete (complete / complete-pass / complete-fail all count as the
 * activity being done). The activity must have completion tracking enabled for this to ever pass.
 *
 * Config: ['cmid' => int]  (the course module id of the assignment)
 */
class assign_completed_rule implements rule_interface {

    public function get_type(): string {
        return 'assign_completed';
    }

    public function get_label(): string {
        return get_string('cert_rule_assign_completed', 'local_academy');
    }

    public function get_config_schema(): array {
        return [[
            'name'    => 'cmid',
            'type'    => 'select_assign',
            'label'   => get_string('cert_rule_assign', 'local_academy'),
            'default' => 0,
        ]];
    }

    public function describe(int $courseid, array $config): string {
        $name = $this->assign_name($courseid, (int)($config['cmid'] ?? 0));
        // Module deleted or never picked — naming nothing is worse than the generic label.
        return $name === '' ? '' : get_string('cert_req_assign_completed', 'local_academy', $name);
    }

    /**
     * The assignment's display name, or '' when the module is gone or unset.
     *
     * @param int $courseid
     * @param int $cmid course module id
     * @return string
     */
    private function assign_name(int $courseid, int $cmid): string {
        if ($cmid <= 0) {
            return '';
        }
        try {
            $cms = get_fast_modinfo($courseid)->get_cms();
        } catch (\Throwable $e) {
            return '';
        }
        return isset($cms[$cmid]) ? format_string($cms[$cmid]->name) : '';
    }

    public function evaluate(int $userid, int $courseid, array $config): bool {
        return $this->completed($userid, $courseid, (int)($config['cmid'] ?? 0));
    }

    public function measure(int $userid, int $courseid, array $config): array {
        return [
            'actual'   => $this->completed($userid, $courseid, (int)($config['cmid'] ?? 0)) ? 1 : 0,
            'required' => 1,
            'unit'     => '',
            'label'    => $this->get_label(),
        ];
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @param int $cmid
     * @return bool
     */
    private function completed(int $userid, int $courseid, int $cmid): bool {
        global $DB;
        if ($cmid <= 0) {
            return false;
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course, $userid);
        $cms = $modinfo->get_cms();
        if (!isset($cms[$cmid])) {
            return false;
        }
        $cm = $cms[$cmid];
        $completion = new \completion_info($course);
        if (!$completion->is_enabled($cm)) {
            return false;
        }
        $data = $completion->get_data($cm, false, $userid);
        return (int)$data->completionstate !== COMPLETION_INCOMPLETE;
    }
}
