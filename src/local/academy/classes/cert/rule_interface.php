<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * Contract every certificate-eligibility rule must implement.
 *
 * A rule answers one business question about a student in a course (e.g. "is course progress at
 * least 90%?"). Rules are pure evaluators — they read data and return a boolean; they never store
 * anything and never know about certificates. The {@see eligibility_manager} combines many rules
 * with an AND/OR operator to decide overall eligibility.
 *
 * To add a NEW rule type you only:
 *   1. add a class in classes/cert/rule/ implementing this interface, and
 *   2. register it in {@see rule_registry}.
 * The evaluation engine ({@see eligibility_manager}) never changes.
 */
interface rule_interface {

    /**
     * Stable machine key for this rule, stored in the ruleset JSON (e.g. 'course_progress').
     *
     * @return string
     */
    public function get_type(): string;

    /**
     * Human-readable, translated label for admin UI / reports.
     *
     * @return string
     */
    public function get_label(): string;

    /**
     * The config fields this rule needs, so the admin UI can render inputs generically without the
     * engine knowing about any specific rule. Each field is:
     *   ['name' => string, 'type' => 'number'|'select_quiz'|'select_assign', 'label' => string,
     *    'default' => mixed, 'min' => ?int, 'max' => ?int]
     *
     * @return array list of field descriptors (empty if the rule needs no config)
     */
    public function get_config_schema(): array;

    /**
     * Does this student satisfy the rule?
     *
     * @param int $userid
     * @param int $courseid
     * @param array $config rule-specific settings (keys per {@see get_config_schema()})
     * @return bool
     */
    public function evaluate(int $userid, int $courseid, array $config): bool;

    /**
     * The requirement stated concretely, for the student.
     *
     * {@see get_label()} names the rule TYPE ("Program progress ≥ threshold %") — useful to an admin
     * picking a rule from a list, useless to a student who needs to know *what to actually do*. This
     * returns the same rule with its configuration and scope filled in ("Complete at least 90% of the
     * program's courses"), so the certificate card can tell them.
     *
     * @param int $scopeid courseid for course-scoped rules, programid for program-scoped ones
     * @param array $config rule-specific settings (keys per {@see get_config_schema()})
     * @return string translated sentence; '' to fall back to the label
     */
    public function describe(int $scopeid, array $config): string;

    /**
     * Actual vs required measurement, for "why eligible / not eligible" reporting.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $config
     * @return array ['actual' => mixed, 'required' => mixed, 'unit' => string, 'label' => string]
     */
    public function measure(int $userid, int $courseid, array $config): array;
}
