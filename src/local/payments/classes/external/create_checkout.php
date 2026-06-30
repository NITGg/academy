<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class create_checkout extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'country' => new \external_value(PARAM_ALPHA, 'Country code from app', VALUE_DEFAULT, ''),
            'lang' => new \external_value(PARAM_ALPHA, 'Display language (en/ar)', VALUE_DEFAULT, 'en'),
        ]);
    }

    public static function execute(int $courseid, string $country = '', string $lang = 'en'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'country' => $country,
            'lang' => $lang,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/payments:purchasecourse', $context);

        $result = \local_payments\manager::create_checkout(
            $params['courseid'],
            $USER->id,
            !empty($params['country']) ? $params['country'] : null,
            $params['lang']
        );

        return [
            'order_id' => $result->order_id,
            'checkout_url' => $result->checkout_url,
            'expires_at' => $result->expires_at,
            'provider' => $result->provider,
            'transaction_id' => $result->transaction_id,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'order_id' => new \external_value(PARAM_TEXT, 'Internal order ID'),
            'checkout_url' => new \external_value(PARAM_URL, 'Provider checkout URL'),
            'expires_at' => new \external_value(PARAM_INT, 'Expiry timestamp'),
            'provider' => new \external_value(PARAM_TEXT, 'Provider name'),
            'transaction_id' => new \external_value(PARAM_INT, 'Transaction record ID'),
        ]);
    }
}
