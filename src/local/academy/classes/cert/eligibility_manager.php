<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate Eligibility Service.
 *
 * The single public entry point for deciding whether a student is eligible for a certificate. It is
 * completely plugin-agnostic: it knows nothing about mod_customcert or any certificate plugin, and
 * only ever answers "is this student eligible?" plus a per-rule breakdown of why.
 *
 * SCOPE — this is an eligibility WRAPPER, not a certificate system. {academy_certificates} is a
 * TEMPORARY abstraction that holds only the Academy-specific eligibility configuration until a real
 * certificate provider is available. It deliberately does NOT (and must never) implement certificate
 * functionality — no templates, PDF generation, verification, downloads or history. When
 * mod_customcert is installed, an Academy certificate simply points at the corresponding Custom
 * Certificate activity via `externalref`, and that plugin stays the single source of truth for
 * certificate generation. Do not grow this table/class into a parallel certificate implementation.
 *
 * Domain model: eligibility rules belong to a CERTIFICATE (wrapper), not a course. A course may have
 * many such wrappers (e.g. Completion, Attendance, Excellence), each with its own ruleset. A wrapper
 * is stored in {academy_certificates}; its row id is the abstract `certificate_id` the engine
 * evaluates, and `externalref` is the cmid of the real Custom Certificate activity it governs (0 =
 * not yet linked). `courseid` is only the context the rules evaluate against.
 *
 * A ruleset is a self-contained structure carried by a certificate:
 *   [ 'operator' => 'and'|'or', 'rules' => [ ['type' => .., 'config' => [..]], .. ] ]
 * The same structure could later move inline into an availability condition on the mapped activity —
 * so no engine change is needed when we gate the real certificate.
 *
 * The evaluation loop here NEVER changes when new rule types are added; rules are resolved through
 * {@see rule_registry}.
 */
class eligibility_manager {

    /** Root operator: every rule must pass. */
    const OP_AND = 'and';
    /** Root operator: at least one rule must pass. */
    const OP_OR = 'or';

    /** Scope: the certificate's rules evaluate against a course (courseid set). */
    const SCOPE_COURSE = 'course';
    /** Scope: the certificate's rules evaluate against an enrol_programs program (programid set). */
    const SCOPE_PROGRAM = 'program';

    /** Storage table (name <= 28 chars). */
    const TABLE = 'academy_certificates';

    /**
     * A certificate's scope, derived from which id it carries.
     *
     * @param \stdClass $cert
     * @return string self::SCOPE_PROGRAM if programid > 0, else self::SCOPE_COURSE
     */
    public static function cert_scope(\stdClass $cert): string {
        return (int)($cert->programid ?? 0) > 0 ? self::SCOPE_PROGRAM : self::SCOPE_COURSE;
    }

    /**
     * The scope id the engine evaluates a certificate against (programid for program certs, else
     * courseid).
     *
     * @param \stdClass $cert
     * @return int
     */
    public static function scope_id(\stdClass $cert): int {
        return (int)($cert->programid ?? 0) > 0 ? (int)$cert->programid : (int)$cert->courseid;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Evaluation engine (rule-type agnostic, certificate/course independent)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate a ruleset structure for a user, in a given scope.
     *
     * A ruleset with no rules is treated as NOT eligible: a certificate must have at least one
     * explicit rule, and we never award one on an empty/misconfigured ruleset.
     *
     * @param int $userid
     * @param int $scopeid the id the rules evaluate against — a courseid for course-scoped rules,
     *                     a programid for program-scoped rules. The rule interprets it by its type.
     * @param array $ruleset ['operator' => 'and'|'or', 'rules' => [['type','config'], ..]]
     * @return array ['eligible' => bool, 'operator' => 'and'|'or', 'results' => [per-rule ..]]
     */
    public static function evaluate_ruleset(int $userid, int $scopeid, array $ruleset): array {
        $operator = (($ruleset['operator'] ?? self::OP_AND) === self::OP_OR) ? self::OP_OR : self::OP_AND;
        $rules = isset($ruleset['rules']) && is_array($ruleset['rules']) ? $ruleset['rules'] : [];

        $results = [];
        $passes  = [];

        foreach ($rules as $r) {
            $type   = is_array($r) ? (string)($r['type'] ?? '') : '';
            $config = is_array($r) && isset($r['config']) && is_array($r['config']) ? $r['config'] : [];

            if ($type === '' || !rule_registry::exists($type)) {
                // Unknown/misconfigured rule fails safe (not eligible) but is still reported.
                $results[] = [
                    'type'     => $type,
                    'passed'   => false,
                    'actual'   => null,
                    'required' => null,
                    'unit'     => '',
                    'label'    => $type,
                    'description' => '',
                    'error'    => 'unknown_rule',
                ];
                $passes[] = false;
                continue;
            }

            $rule = rule_registry::get($type);
            try {
                $passed = $rule->evaluate($userid, $scopeid, $config);
                $m = $rule->measure($userid, $scopeid, $config);
            } catch (\Throwable $e) {
                // A rule that errors (e.g. references a deleted activity) fails safe.
                $passed = false;
                $m = ['actual' => null, 'required' => null, 'unit' => '', 'label' => $rule->get_label()];
            }

            // The plain-language requirement. Guarded separately from evaluate/measure so a rule that
            // cannot name its target (deleted quiz, program with no courses) still reports its
            // result — the student sees the generic label instead of losing the row entirely.
            // method_exists keeps a rule registered by another plugin, written against the older
            // interface, working rather than fataling.
            $description = '';
            if (method_exists($rule, 'describe')) {
                try {
                    $description = (string)$rule->describe($scopeid, $config);
                } catch (\Throwable $e) {
                    $description = '';
                }
            }

            $results[] = [
                'type'     => $type,
                'passed'   => (bool)$passed,
                'actual'   => $m['actual'] ?? null,
                'required' => $m['required'] ?? null,
                'unit'     => $m['unit'] ?? '',
                'label'    => $m['label'] ?? $rule->get_label(),
                // What the student must do, config filled in. '' → fall back to `label`.
                'description' => $description,
            ];
            $passes[] = (bool)$passed;
        }

        if (empty($passes)) {
            $eligible = false;
        } else if ($operator === self::OP_OR) {
            $eligible = in_array(true, $passes, true);
        } else {
            $eligible = !in_array(false, $passes, true);
        }

        return ['eligible' => $eligible, 'operator' => $operator, 'results' => $results];
    }

    /**
     * Evaluate an ad-hoc ruleset structure (not stored). Used by tests and a future availability
     * adapter that carries inline ruleset JSON.
     *
     * @param int $userid
     * @param int $scopeid courseid or programid depending on the rules' scope
     * @param array $ruleset
     * @return array
     */
    public static function evaluate_adhoc(int $userid, int $scopeid, array $ruleset): array {
        return self::evaluate_ruleset($userid, $scopeid, $ruleset);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Certificate-centric API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Simple yes/no eligibility for a certificate.
     *
     * @param int $userid
     * @param int $certificateid the {academy_certificates}.id
     * @return bool false if the certificate is missing or disabled.
     */
    public static function is_eligible(int $userid, int $certificateid): bool {
        $cert = self::get_certificate($certificateid);
        if (!$cert || empty($cert->enabled)) {
            return false;
        }
        self::sync_scope($userid, $cert);
        return self::evaluate_ruleset($userid, self::scope_id($cert), self::decode_ruleset($cert))['eligible'];
    }

    /**
     * Refresh the completion flags this certificate's rules read, before evaluating them.
     *
     * The rules are pure readers of Moodle's stored completion state, and that state is only written
     * by cron. Without this, a student who has genuinely finished everything stays "not eligible"
     * until the next cron run — and indefinitely if cron is stopped. See {@see completion_sync}.
     *
     * @param int $userid
     * @param \stdClass $cert
     */
    private static function sync_scope(int $userid, \stdClass $cert): void {
        // Refreshing is an optimisation, never a precondition: if it fails for any reason the
        // certificate must still be reported from the flags already stored. Callers render a student
        // -facing card from this, and a broken refresh must not make that card disappear.
        try {
            if (self::cert_scope($cert) === self::SCOPE_PROGRAM) {
                completion_sync::sync_program($userid, (int)$cert->programid);
            } else {
                completion_sync::sync_course($userid, (int)$cert->courseid);
            }
        } catch (\Throwable $e) {
            debugging('local_academy: completion sync skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Full eligibility report for a certificate (overall flag + per-rule breakdown + meta).
     *
     * @param int $userid
     * @param int $certificateid
     * @return array
     * @throws \moodle_exception if the certificate does not exist.
     */
    public static function get_report(int $userid, int $certificateid): array {
        $cert = self::get_certificate($certificateid);
        if (!$cert) {
            throw new \moodle_exception('err_certnotfound', 'local_academy');
        }
        self::sync_scope($userid, $cert);
        $out = self::evaluate_ruleset($userid, self::scope_id($cert), self::decode_ruleset($cert));
        $out['certificateid'] = (int)$cert->id;
        $out['courseid']      = (int)$cert->courseid;
        $out['programid']     = (int)($cert->programid ?? 0);
        $out['scope']         = self::cert_scope($cert);
        $out['name']          = $cert->name;
        $out['type']          = $cert->type;
        $out['externalref']   = (int)$cert->externalref;
        $out['enabled']       = (bool)$cert->enabled;
        // A disabled certificate never grants eligibility, regardless of rule results.
        if (empty($cert->enabled)) {
            $out['eligible'] = false;
        }
        return $out;
    }

    /**
     * Eligibility reports for every certificate in a course (so a student can see all of them).
     *
     * @param int $userid
     * @param int $courseid
     * @return array list of reports (see {@see get_report()}).
     */
    public static function get_course_certificate_reports(int $userid, int $courseid): array {
        $out = [];
        foreach (self::get_course_certificates($courseid) as $cert) {
            $out[] = self::get_report($userid, (int)$cert->id);
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Storage (a certificate = one self-contained ruleset; many per course)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param int $certificateid
     * @return \stdClass|null
     */
    public static function get_certificate(int $certificateid): ?\stdClass {
        global $DB;
        $rec = $DB->get_record(self::TABLE, ['id' => $certificateid]);
        return $rec ?: null;
    }

    /**
     * @param int $courseid
     * @return \stdClass[] the course's certificates (ordered by name). Excludes program certs.
     */
    public static function get_course_certificates(int $courseid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE,
            ['courseid' => $courseid, 'programid' => 0], 'name ASC, id ASC'));
    }

    /**
     * @param int $programid
     * @return \stdClass[] the program's certificates (ordered by name).
     */
    public static function get_program_certificates(int $programid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['programid' => $programid], 'name ASC, id ASC'));
    }

    /**
     * Eligibility reports for every certificate on a program.
     *
     * @param int $userid
     * @param int $programid
     * @return array list of reports (see {@see get_report()}).
     */
    public static function get_program_certificate_reports(int $userid, int $programid): array {
        $out = [];
        foreach (self::get_program_certificates($programid) as $cert) {
            $out[] = self::get_report($userid, (int)$cert->id);
        }
        return $out;
    }

    /**
     * Decode a stored certificate into the ruleset structure the engine consumes.
     *
     * @param \stdClass $cert
     * @return array ['operator' => .., 'rules' => [..]]
     */
    public static function decode_ruleset(\stdClass $cert): array {
        $rules = json_decode($cert->rules ?? '[]', true);
        if (!is_array($rules)) {
            $rules = [];
        }
        return [
            'operator' => ($cert->operator === self::OP_OR) ? self::OP_OR : self::OP_AND,
            'rules'    => $rules,
        ];
    }

    /**
     * Create or update a certificate + its eligibility rules.
     *
     * A certificate is scoped to a course OR an enrol_programs program — exactly one of courseid /
     * programid must be set, and every rule must belong to that scope (a program cert cannot carry
     * course rules and vice-versa).
     *
     * @param array $data keys: id (0/absent = create), courseid, programid, name, type, externalref,
     *                    operator ('and'|'or'), enabled (bool), rules (list of ['type','config'])
     * @param int $userid admin performing the change
     * @return int certificate id
     * @throws \moodle_exception on invalid input.
     */
    public static function save_certificate(array $data, int $userid): int {
        global $DB;

        $courseid  = (int)($data['courseid'] ?? 0);
        $programid = (int)($data['programid'] ?? 0);

        // Exactly one scope. Guard against both-or-neither so a certificate is never ambiguous.
        if (($courseid > 0) === ($programid > 0)) {
            throw new \moodle_exception('err_certscopeinvalid', 'local_academy');
        }
        if ($programid > 0) {
            if (!$DB->record_exists('enrol_programs_programs', ['id' => $programid])) {
                throw new \moodle_exception('err_certprogramnotfound', 'local_academy');
            }
            $scope = self::SCOPE_PROGRAM;
        } else {
            if (!$DB->record_exists('course', ['id' => $courseid])) {
                throw new \moodle_exception('err_certcoursenotfound', 'local_academy');
            }
            $scope = self::SCOPE_COURSE;
        }

        $operator = (($data['operator'] ?? self::OP_AND) === self::OP_OR) ? self::OP_OR : self::OP_AND;
        $clean = self::sanitise_rules(is_array($data['rules'] ?? null) ? $data['rules'] : [], $scope);
        $now = time();

        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $existing = self::get_certificate($id);
            if (!$existing) {
                throw new \moodle_exception('err_certnotfound', 'local_academy');
            }
            $existing->courseid     = $courseid;
            $existing->programid    = $programid;
            $existing->name         = (string)($data['name'] ?? $existing->name);
            $existing->type         = (string)($data['type'] ?? $existing->type);
            $existing->externalref  = (int)($data['externalref'] ?? $existing->externalref);
            $existing->operator     = $operator;
            $existing->rules        = json_encode($clean);
            $existing->enabled      = !empty($data['enabled']) ? 1 : 0;
            $existing->timemodified = $now;
            $existing->usermodified = $userid;
            $DB->update_record(self::TABLE, $existing);
            return (int)$existing->id;
        }

        $rec = new \stdClass();
        $rec->courseid     = $courseid;
        $rec->programid    = $programid;
        $rec->name         = (string)($data['name'] ?? '');
        $rec->type         = (string)($data['type'] ?? 'completion');
        $rec->externalref  = (int)($data['externalref'] ?? 0);
        $rec->operator     = $operator;
        $rec->rules        = json_encode($clean);
        $rec->enabled      = !empty($data['enabled']) ? 1 : 0;
        $rec->timecreated  = $now;
        $rec->timemodified = $now;
        $rec->usermodified = $userid;
        return (int)$DB->insert_record(self::TABLE, $rec);
    }

    /**
     * Delete a certificate and its rules.
     *
     * @param int $certificateid
     */
    public static function delete_certificate(int $certificateid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $certificateid]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Support helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Quizzes and assignments in a course, for populating the admin rule-config dropdowns.
     *
     * @param int $courseid
     * @return array ['quizzes' => [['quizid','cmid','name'], ..], 'assigns' => [['cmid','name'], ..]]
     */
    public static function get_course_activities(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $quizzes = [];
        $assigns = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }
            if ($cm->modname === 'quiz') {
                $quizzes[] = ['quizid' => (int)$cm->instance, 'cmid' => (int)$cm->id, 'name' => format_string($cm->name)];
            } else if ($cm->modname === 'assign') {
                $assigns[] = ['cmid' => (int)$cm->id, 'name' => format_string($cm->name)];
            }
        }
        return ['quizzes' => $quizzes, 'assigns' => $assigns];
    }

    /**
     * Validate + normalise a list of rules against the registry, keeping only the config keys each
     * rule declares.
     *
     * @param array $rules
     * @param string|null $scope if given (SCOPE_COURSE / SCOPE_PROGRAM), every rule must belong to
     *                    that scope — a certificate may not mix course and program rules.
     * @return array cleaned list of ['type' => .., 'config' => [..]]
     * @throws \moodle_exception if a rule references an unknown type or the wrong scope.
     */
    public static function sanitise_rules(array $rules, ?string $scope = null): array {
        $out = [];
        foreach ($rules as $r) {
            $type = is_array($r) ? (string)($r['type'] ?? '') : '';
            if ($type === '' || !rule_registry::exists($type)) {
                throw new \moodle_exception('err_certruleunknown', 'local_academy', '', $type);
            }
            if ($scope !== null && rule_registry::scope_of($type) !== $scope) {
                throw new \moodle_exception('err_certscopemismatch', 'local_academy', '', $type);
            }
            $rule = rule_registry::get($type);
            $incoming = is_array($r) && isset($r['config']) && is_array($r['config']) ? $r['config'] : [];
            $config = [];
            foreach ($rule->get_config_schema() as $field) {
                $key = $field['name'];
                // A blank field ('') is "not provided", not "zero" — otherwise an admin who leaves the
                // threshold empty silently stores 0, and a "progress ≥ 0%" rule then passes for
                // everyone. Fall back to the field's default (e.g. 90%) instead.
                $provided = array_key_exists($key, $incoming) && $incoming[$key] !== '';
                if ($provided) {
                    $config[$key] = ($field['type'] === 'number')
                        ? (float)$incoming[$key]
                        : (int)$incoming[$key];
                } else if (array_key_exists('default', $field)) {
                    $config[$key] = $field['default'];
                }
            }
            $out[] = ['type' => $type, 'config' => $config];
        }
        return $out;
    }
}
