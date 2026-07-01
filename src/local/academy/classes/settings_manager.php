<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Platform lesson + financial settings (US-AD-2-1). Stored as plugin config.
 */
class settings_manager {

    /** Setting key => default value. */
    const DEFAULTS = array(
        'min_booking_minutes'     => 60,   // minimum lesson booking lead time
        'cancel_deadline_minutes' => 120,  // student cancellation deadline before start
        'update_deadline_minutes' => 120,  // lesson time-update deadline before start
        'start_allowed_minutes'   => 30,   // how early a lesson may start / link visible
        'complete_allowed_minutes' => 180, // how long after the start a lesson may still be completed
        'absence_report_minutes'  => 15,   // wait before reporting absence
        'expiry_reminder_days'    => 3,    // notify a student this many days before a package expires (0 = off)
        'teacher_percent'         => 40,   // teacher share of a consumed Flex
        'platform_percent'        => 60,   // platform share of a consumed Flex
        'lessons_courseid'        => 54,   // course that hosts the per-lesson Jitsi meeting rooms (hardcoded for now)
    );

    /** Return all settings (defaults applied). */
    public static function get_settings() {
        $out = array();
        foreach (self::DEFAULTS as $key => $default) {
            $val = get_config('local_academy', $key);
            $out[$key] = ($val === false || $val === null || $val === '') ? $default : (int)$val;
        }
        return $out;
    }

    /** A single setting value (default applied). */
    public static function get($key) {
        $all = self::get_settings();
        return isset($all[$key]) ? $all[$key] : null;
    }

    /**
     * Update settings (US-AD-2-1). Only provided keys are changed.
     *
     * @param array $data subset of DEFAULTS keys
     */
    public static function update_settings(array $data) {
        $current = self::get_settings();

        // Validate deadline-style values are >= 0.
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue; // ignore unknown keys
            }
            if ((int)$value < 0) {
                throw new \moodle_exception('err_settingnegative', 'local_academy');
            }
            $current[$key] = (int)$value;
        }

        // Teacher % + platform % must total 100.
        if ($current['teacher_percent'] + $current['platform_percent'] !== 100) {
            throw new \moodle_exception('err_percenttotal', 'local_academy');
        }

        foreach ($data as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                set_config($key, (int)$value, 'local_academy');
            }
        }
        return self::get_settings();
    }
}
