<?php
namespace local_academy;

use local_academy\cert\eligibility_manager;
use local_academy\cert\rule_registry;
use local_academy\cert\rule_interface;

defined('MOODLE_INTERNAL') || die();

/**
 * A dummy rule that always returns a fixed result — lets us test the engine's AND/OR combining
 * without depending on any real Moodle data, and proves a NEW rule type works purely via
 * rule_registry::register() with no change to the engine.
 */
class cert_fixed_rule implements rule_interface {
    /** @var bool */
    public static $result = true;
    public function get_type(): string { return 'cert_fixed'; }
    public function get_label(): string { return 'Fixed'; }
    public function get_config_schema(): array {
        return [['name' => 'pass', 'type' => 'number', 'label' => 'pass', 'default' => 1]];
    }
    public function evaluate(int $userid, int $courseid, array $config): bool {
        // If a config 'pass' is given, use it; else the static default.
        return array_key_exists('pass', $config) ? ((int)$config['pass'] === 1) : self::$result;
    }
    public function measure(int $userid, int $courseid, array $config): array {
        return ['actual' => 1, 'required' => 1, 'unit' => '', 'label' => 'Fixed'];
    }
}

/**
 * Unit tests for the plugin-agnostic certificate eligibility engine and rules.
 *
 * @covers \local_academy\cert\eligibility_manager
 * @covers \local_academy\cert\rule_registry
 */
final class cert_eligibility_test extends \advanced_testcase {

    protected function tearDown(): void {
        rule_registry::reset_runtime();
        parent::tearDown();
    }

    /** A ruleset structure of N fixed rules with the given pass flags. */
    private function ruleset(string $operator, array $passflags): array {
        $rules = [];
        foreach ($passflags as $p) {
            $rules[] = ['type' => 'cert_fixed', 'config' => ['pass' => $p ? 1 : 0]];
        }
        return ['operator' => $operator, 'rules' => $rules];
    }

    public function test_extensibility_new_rule_without_engine_change(): void {
        $this->resetAfterTest();
        // Register a brand-new rule type at runtime — the engine (eligibility_manager) is untouched.
        rule_registry::register('cert_fixed', cert_fixed_rule::class);
        $this->assertTrue(rule_registry::exists('cert_fixed'));

        $out = eligibility_manager::evaluate_adhoc(1, 1, $this->ruleset('and', [true]));
        $this->assertTrue($out['eligible']);
        $this->assertSame('cert_fixed', $out['results'][0]['type']);
    }

    public function test_and_requires_all(): void {
        $this->resetAfterTest();
        rule_registry::register('cert_fixed', cert_fixed_rule::class);

        $this->assertTrue(eligibility_manager::evaluate_adhoc(1, 1, $this->ruleset('and', [true, true, true]))['eligible']);
        $this->assertFalse(eligibility_manager::evaluate_adhoc(1, 1, $this->ruleset('and', [true, false, true]))['eligible']);
    }

    public function test_or_requires_any(): void {
        $this->resetAfterTest();
        rule_registry::register('cert_fixed', cert_fixed_rule::class);

        $this->assertTrue(eligibility_manager::evaluate_adhoc(1, 1, $this->ruleset('or', [false, false, true]))['eligible']);
        $this->assertFalse(eligibility_manager::evaluate_adhoc(1, 1, $this->ruleset('or', [false, false, false]))['eligible']);
    }

    public function test_empty_ruleset_not_eligible(): void {
        $this->resetAfterTest();
        $out = eligibility_manager::evaluate_adhoc(1, 1, ['operator' => 'and', 'rules' => []]);
        $this->assertFalse($out['eligible']);
    }

    public function test_unknown_rule_fails_safe(): void {
        $this->resetAfterTest();
        $out = eligibility_manager::evaluate_adhoc(1, 1, ['operator' => 'and', 'rules' => [
            ['type' => 'no_such_rule', 'config' => []],
        ]]);
        $this->assertFalse($out['eligible']);
        $this->assertSame('unknown_rule', $out['results'][0]['error']);
    }

    /** Shorthand to save a certificate with a single fixed rule. */
    private function save_cert(int $courseid, bool $pass, bool $enabled = true, string $name = 'Cert', int $id = 0): int {
        return eligibility_manager::save_certificate([
            'id' => $id, 'courseid' => $courseid, 'name' => $name, 'type' => 'completion',
            'operator' => 'and', 'enabled' => $enabled,
            'rules' => [['type' => 'cert_fixed', 'config' => ['pass' => $pass ? 1 : 0]]],
        ], 2);
    }

    public function test_certificate_storage_and_reports(): void {
        $this->resetAfterTest();
        rule_registry::register('cert_fixed', cert_fixed_rule::class);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create a passing certificate.
        $id = $this->save_cert($course->id, true);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue(eligibility_manager::is_eligible($user->id, $id));

        $report = eligibility_manager::get_report($user->id, $id);
        $this->assertSame($id, $report['certificateid']);
        $this->assertSame($course->id, $report['courseid']);
        $this->assertTrue($report['eligible']);

        // Update the SAME certificate to failing (edit by id) — not a new row.
        $id2 = $this->save_cert($course->id, false, true, 'Cert', $id);
        $this->assertSame($id, $id2);
        $this->assertFalse(eligibility_manager::is_eligible($user->id, $id));

        // Disabled certificate is never eligible.
        $this->save_cert($course->id, true, false, 'Cert', $id);
        $this->assertFalse(eligibility_manager::is_eligible($user->id, $id));

        // Delete it.
        eligibility_manager::delete_certificate($id);
        $this->assertNull(eligibility_manager::get_certificate($id));
    }

    public function test_multiple_certificates_per_course(): void {
        $this->resetAfterTest();
        rule_registry::register('cert_fixed', cert_fixed_rule::class);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Same course, two certificates with different rules — the whole point of the refactor.
        $pass = $this->save_cert($course->id, true, true, 'Completion');
        $fail = $this->save_cert($course->id, false, true, 'Excellence');

        $this->assertCount(2, eligibility_manager::get_course_certificates($course->id));
        $this->assertTrue(eligibility_manager::is_eligible($user->id, $pass));
        $this->assertFalse(eligibility_manager::is_eligible($user->id, $fail));

        // A per-course report lists every certificate independently.
        $reports = eligibility_manager::get_course_certificate_reports($user->id, $course->id);
        $this->assertCount(2, $reports);
        $byname = [];
        foreach ($reports as $r) { $byname[$r['name']] = $r['eligible']; }
        $this->assertTrue($byname['Completion']);
        $this->assertFalse($byname['Excellence']);
    }

    public function test_get_report_missing_certificate_throws(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        eligibility_manager::get_report(1, 999999);
    }

    public function test_save_rejects_unknown_rule(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\moodle_exception::class);
        eligibility_manager::save_certificate([
            'courseid' => $course->id, 'name' => 'X', 'operator' => 'and', 'enabled' => true,
            'rules' => [['type' => 'ghost', 'config' => []]],
        ], 2);
    }

    public function test_course_progress_rule_threshold(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Two completion-tracked activities; complete one → 50% progress.
        $m1 = $this->getDataGenerator()->create_module('data',
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->getDataGenerator()->create_module('data',
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]);

        $completion = new \completion_info($course);
        $cm1 = get_coursemodule_from_id('data', $m1->cmid);
        $completion->update_state($cm1, COMPLETION_COMPLETE, $user->id);

        $rule = new cert\rule\course_progress_rule();
        $this->assertTrue($rule->evaluate($user->id, $course->id, ['threshold' => 50]));
        $this->assertFalse($rule->evaluate($user->id, $course->id, ['threshold' => 90]));

        $m = $rule->measure($user->id, $course->id, ['threshold' => 90]);
        $this->assertEquals(50.0, $m['actual']);
        $this->assertEquals(90.0, $m['required']);
        $this->assertSame('%', $m['unit']);
    }

    public function test_attendance_rule_percentage(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $now = time();

        // Two completed sessions the student is assigned to; they attended one → 50%.
        $s1 = $DB->insert_record('academy_live_sessions', (object)[
            'courseid' => $course->id, 'teacherid' => 2, 'title' => 'S1', 'start_time' => $now,
            'duration' => 50, 'status' => 'completed', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $s2 = $DB->insert_record('academy_live_sessions', (object)[
            'courseid' => $course->id, 'teacherid' => 2, 'title' => 'S2', 'start_time' => $now,
            'duration' => 50, 'status' => 'completed', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        foreach ([$s1, $s2] as $sid) {
            $DB->insert_record('academy_session_students', (object)['sessionid' => $sid, 'userid' => $user->id]);
        }
        $DB->insert_record('academy_session_attendance', (object)[
            'sessionid' => $s1, 'userid' => $user->id, 'joined_at' => $now, 'duration_seconds' => 3000,
        ]);

        $rule = new cert\rule\attendance_rule();
        $this->assertTrue($rule->evaluate($user->id, $course->id, ['threshold' => 50]));
        $this->assertFalse($rule->evaluate($user->id, $course->id, ['threshold' => 70]));
        $this->assertEquals(50.0, $rule->measure($user->id, $course->id, ['threshold' => 70])['actual']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Program-scoped rules + certificates
    // ──────────────────────────────────────────────────────────────────────────

    public function test_program_progress_and_courses_completed_rules(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enablecompletion = true;

        $gen = $this->getDataGenerator();
        /** @var \enrol_programs_generator $pgen */
        $pgen = $gen->get_plugin_generator('enrol_programs');

        $c1 = $gen->create_course(['enablecompletion' => 1]);
        $c2 = $gen->create_course(['enablecompletion' => 1]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $c1->id);
        $gen->enrol_user($user->id, $c2->id);

        $program = $pgen->create_program(['fullname' => 'Prog A']);
        $pgen->create_program_item(['programid' => $program->id, 'courseid' => $c1->id]);
        $pgen->create_program_item(['programid' => $program->id, 'courseid' => $c2->id]);
        $pgen->create_program_allocation(['programid' => $program->id, 'userid' => $user->id]);

        $progress = new cert\rule\program_progress_rule();
        $coursesdone = new cert\rule\program_courses_completed_rule();

        // Nothing complete yet → 0% and not all courses done.
        $this->assertEquals(0.0, $progress->measure($user->id, $program->id, ['threshold' => 50])['actual']);
        $this->assertFalse($coursesdone->evaluate($user->id, $program->id, []));

        // Complete one of two courses → 50%.
        (new \completion_completion(['course' => $c1->id, 'userid' => $user->id]))->mark_complete();

        $this->assertTrue($progress->evaluate($user->id, $program->id, ['threshold' => 50]));
        $this->assertFalse($progress->evaluate($user->id, $program->id, ['threshold' => 90]));
        $this->assertEquals(50.0, $progress->measure($user->id, $program->id, ['threshold' => 90])['actual']);
        $this->assertFalse($coursesdone->evaluate($user->id, $program->id, []));

        // Complete the second → 100% and all courses done.
        (new \completion_completion(['course' => $c2->id, 'userid' => $user->id]))->mark_complete();

        $this->assertEquals(100.0, $progress->measure($user->id, $program->id, ['threshold' => 90])['actual']);
        $this->assertTrue($coursesdone->evaluate($user->id, $program->id, []));
        $m = $coursesdone->measure($user->id, $program->id, []);
        $this->assertEquals(2, $m['actual']);
        $this->assertEquals(2, $m['required']);
    }

    public function test_program_completed_rule(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        /** @var \enrol_programs_generator $pgen */
        $pgen = $gen->get_plugin_generator('enrol_programs');
        $user = $gen->create_user();
        $program = $pgen->create_program(['fullname' => 'Prog B']);
        $alloc = $pgen->create_program_allocation(['programid' => $program->id, 'userid' => $user->id]);

        $rule = new cert\rule\program_completed_rule();

        // Not completed → false (reset deterministically in case allocation flow set anything).
        $DB->set_field('enrol_programs_allocations', 'timecompleted', null, ['id' => $alloc->id]);
        $this->assertFalse($rule->evaluate($user->id, $program->id, []));

        // Program allocation marked complete → true.
        $DB->set_field('enrol_programs_allocations', 'timecompleted', time(), ['id' => $alloc->id]);
        $this->assertTrue($rule->evaluate($user->id, $program->id, []));
        $this->assertEquals(1, $rule->measure($user->id, $program->id, [])['actual']);
    }

    public function test_save_program_certificate_reports_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        /** @var \enrol_programs_generator $pgen */
        $pgen = $gen->get_plugin_generator('enrol_programs');
        $user = $gen->create_user();
        $program = $pgen->create_program(['fullname' => 'Prog C']);

        $id = eligibility_manager::save_certificate([
            'programid' => $program->id, 'name' => 'Program Completion', 'type' => 'completion',
            'operator' => 'and', 'enabled' => true,
            'rules' => [['type' => 'program_completed', 'config' => []]],
        ], 2);
        $this->assertGreaterThan(0, $id);

        $reports = eligibility_manager::get_program_certificate_reports($user->id, $program->id);
        $this->assertCount(1, $reports);
        $this->assertSame('program', $reports[0]['scope']);
        $this->assertSame((int)$program->id, $reports[0]['programid']);
        // Program certs never appear in a course listing.
        $this->assertCount(0, eligibility_manager::get_course_certificates((int)$program->id));
    }

    public function test_save_certificate_rejects_scope_mismatch(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        /** @var \enrol_programs_generator $pgen */
        $pgen = $this->getDataGenerator()->get_plugin_generator('enrol_programs');
        $program = $pgen->create_program(['fullname' => 'Prog D']);

        $this->expectException(\moodle_exception::class);
        // A program cert may not carry a course-scoped rule.
        eligibility_manager::save_certificate([
            'programid' => $program->id, 'name' => 'X', 'operator' => 'and', 'enabled' => true,
            'rules' => [['type' => 'course_completed', 'config' => []]],
        ], 2);
    }

    public function test_save_certificate_requires_exactly_one_scope(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        // Neither courseid nor programid set → ambiguous scope, rejected.
        eligibility_manager::save_certificate([
            'name' => 'X', 'operator' => 'and', 'enabled' => true,
            'rules' => [['type' => 'course_completed', 'config' => []]],
        ], 2);
    }
}
