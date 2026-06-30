<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class get_payment_methods extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'country' => new \external_value(PARAM_ALPHA, 'Country code from app', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, string $country = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'country' => $country,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);

        $pricing = \local_payments\price_resolver::resolve(
            $params['courseid'], $USER->id,
            !empty($params['country']) ? $params['country'] : null
        );

        return \local_payments\manager::get_available_providers($pricing->country, $pricing->currency);
    }

    public static function execute_returns(): \external_multiple_structure {
        return new \external_multiple_structure(
            new \external_single_structure([
                'name' => new \external_value(PARAM_TEXT, 'Provider name'),
                'display_name' => new \external_value(PARAM_TEXT, 'Display name'),
                'priority' => new \external_value(PARAM_INT, 'Priority'),
            ])
        );
    }
}
