<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * Maps a rule type key to its implementing class and hands back rule instances.
 *
 * This is the ONLY place that needs editing to add a built-in rule type. Other plugins (or tests)
 * may contribute rules at runtime via {@see register()} without touching any file here — proving the
 * engine is open for extension and closed for modification.
 */
class rule_registry {

    /** A rule that evaluates against a single course (2nd engine arg = courseid). */
    const SCOPE_COURSE = 'course';
    /** A rule that evaluates against an enrol_programs program (2nd engine arg = programid). */
    const SCOPE_PROGRAM = 'program';

    /** @var array<string,string> runtime-registered type => classname (tests / other plugins). */
    private static $extra = [];

    /**
     * Built-in rule types mapped to [classname, scope]. Add a new built-in rule by adding one line
     * here. The scope tells the engine/UI whether a rule evaluates against a course or a program;
     * a certificate may only carry rules of its own scope (see {@see eligibility_manager}).
     *
     * @return array<string,array{0:string,1:string}> type => [classname, scope]
     */
    private static function builtin(): array {
        return [
            'course_progress'           => [rule\course_progress_rule::class, self::SCOPE_COURSE],
            'attendance'                => [rule\attendance_rule::class, self::SCOPE_COURSE],
            'quiz_passed'               => [rule\quiz_passed_rule::class, self::SCOPE_COURSE],
            'assign_completed'          => [rule\assign_completed_rule::class, self::SCOPE_COURSE],
            'course_completed'          => [rule\course_completed_rule::class, self::SCOPE_COURSE],
            'program_completed'         => [rule\program_completed_rule::class, self::SCOPE_PROGRAM],
            'program_progress'          => [rule\program_progress_rule::class, self::SCOPE_PROGRAM],
            'program_courses_completed' => [rule\program_courses_completed_rule::class, self::SCOPE_PROGRAM],
        ];
    }

    /**
     * Register a rule type at runtime (extensibility hook for other plugins / unit tests).
     *
     * @param string $type
     * @param string $classname a class implementing {@see rule_interface}
     * @param string $scope self::SCOPE_COURSE (default) or self::SCOPE_PROGRAM
     */
    public static function register(string $type, string $classname, string $scope = self::SCOPE_COURSE): void {
        self::$extra[$type] = [$classname, $scope];
    }

    /**
     * Forget any runtime-registered rules (used by tests to keep isolation).
     */
    public static function reset_runtime(): void {
        self::$extra = [];
    }

    /**
     * @return array<string,array{0:string,1:string}> the full type => [classname, scope] map.
     */
    private static function map(): array {
        return self::$extra + self::builtin();
    }

    /**
     * @param string $type
     * @return bool whether a rule of this type is known.
     */
    public static function exists(string $type): bool {
        $map = self::map();
        return isset($map[$type]);
    }

    /**
     * The scope a rule type evaluates against.
     *
     * @param string $type
     * @return string self::SCOPE_COURSE or self::SCOPE_PROGRAM
     * @throws \moodle_exception if the type is unknown.
     */
    public static function scope_of(string $type): string {
        $map = self::map();
        if (!isset($map[$type])) {
            throw new \moodle_exception('err_certruleunknown', 'local_academy', '', $type);
        }
        return $map[$type][1];
    }

    /**
     * Instantiate a rule by type.
     *
     * @param string $type
     * @return rule_interface
     * @throws \moodle_exception if the type is unknown.
     */
    public static function get(string $type): rule_interface {
        $map = self::map();
        if (!isset($map[$type])) {
            throw new \moodle_exception('err_certruleunknown', 'local_academy', '', $type);
        }
        $classname = $map[$type][0];
        return new $classname();
    }

    /**
     * All known rules, instantiated — for building the admin "add rule" UI.
     *
     * @return array<string,rule_interface> type => instance
     */
    public static function all(): array {
        $out = [];
        foreach (array_keys(self::map()) as $type) {
            $out[$type] = self::get($type);
        }
        return $out;
    }

    /**
     * Compact catalogue of rule types for the admin UI: type, label, scope and config schema.
     *
     * @param string|null $scope optionally restrict to one scope (SCOPE_COURSE / SCOPE_PROGRAM)
     * @return array list of ['type' => .., 'label' => .., 'scope' => .., 'fields' => [..]]
     */
    public static function catalogue(?string $scope = null): array {
        $out = [];
        foreach (self::all() as $type => $rule) {
            $rulescope = self::scope_of($type);
            if ($scope !== null && $rulescope !== $scope) {
                continue;
            }
            $out[] = [
                'type'   => $type,
                'label'  => $rule->get_label(),
                'scope'  => $rulescope,
                'fields' => $rule->get_config_schema(),
            ];
        }
        return $out;
    }
}
