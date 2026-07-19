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

    /** @var array<string,string> runtime-registered type => classname (tests / other plugins). */
    private static $extra = [];

    /**
     * Built-in rule types. Add a new built-in rule by adding one line here.
     *
     * @return array<string,string> type => fully-qualified classname
     */
    private static function builtin(): array {
        return [
            'course_progress'  => rule\course_progress_rule::class,
            'attendance'       => rule\attendance_rule::class,
            'quiz_passed'      => rule\quiz_passed_rule::class,
            'assign_completed' => rule\assign_completed_rule::class,
            'course_completed' => rule\course_completed_rule::class,
        ];
    }

    /**
     * Register a rule type at runtime (extensibility hook for other plugins / unit tests).
     *
     * @param string $type
     * @param string $classname a class implementing {@see rule_interface}
     */
    public static function register(string $type, string $classname): void {
        self::$extra[$type] = $classname;
    }

    /**
     * Forget any runtime-registered rules (used by tests to keep isolation).
     */
    public static function reset_runtime(): void {
        self::$extra = [];
    }

    /**
     * @return array<string,string> the full type => classname map (built-ins + runtime).
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
        return new $map[$type]();
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
     * Compact catalogue of rule types for the admin UI: type, label and config schema.
     *
     * @return array list of ['type' => .., 'label' => .., 'fields' => [..]]
     */
    public static function catalogue(): array {
        $out = [];
        foreach (self::all() as $type => $rule) {
            $out[] = [
                'type'   => $type,
                'label'  => $rule->get_label(),
                'fields' => $rule->get_config_schema(),
            ];
        }
        return $out;
    }
}
