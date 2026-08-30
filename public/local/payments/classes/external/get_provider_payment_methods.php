<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

global $CFG;

/**
 * List the payment methods (Visa/Mastercard, Fawry, Meeza, wallets …) the active
 * gateway offers, so the app can render its own picker and then charge one
 * directly instead of opening a gateway-hosted page.
 *
 * Providers that only offer a hosted picker (Kashier) answer with
 * supports_payment_methods = false and an empty list — the app should fall
 * straight through to create_checkout and open checkout_url.
 */
class get_provider_payment_methods extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT,
                'Course the buyer is paying for. 0 to resolve by country/currency alone '
                . '(e.g. for a subscription).', VALUE_DEFAULT, 0),
            'country' => new external_value(PARAM_ALPHA, 'ISO country from the app (optional)', VALUE_DEFAULT, ''),
            'currency' => new external_value(PARAM_ALPHA,
                'Currency to pay in. Only used when courseid is 0.', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG, 'Display language (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid = 0, string $country = '', string $currency = '',
            string $lang = '', string $alang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'country' => $country,
            'currency' => $currency,
            'lang' => $lang,
            'alang' => $alang,
        ]);

        // System context: the buyer is not enrolled yet, so validating the course
        // context would reject them (same reasoning as get_payment_methods).
        if ($params['courseid'] > 0) {
            \context_course::instance($params['courseid']);
        }
        self::validate_context(\context_system::instance());

        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        if ($params['courseid'] > 0) {
            // Resolve exactly the country/currency this buyer would be charged in,
            // so the list matches the gateway that create_checkout will pick.
            $pricing = \local_payments\price_resolver::resolve(
                $params['courseid'], $USER->id,
                !empty($params['country']) ? $params['country'] : null
            );
            $usecountry = $pricing->country;
            $usecurrency = $pricing->currency;
        } else {
            $usecountry = $params['country'] ?: (string) (get_config('local_payments', 'default_country') ?: 'EG');
            $usecurrency = $params['currency'] ?: (string) (get_config('local_payments', 'default_currency') ?: 'EGP');
        }

        $result = \local_payments\manager::get_provider_payment_methods($usecountry, strtoupper($usecurrency));

        return [
            'provider' => $result->provider,
            'supports_payment_methods' => $result->supports_payment_methods,
            'methods' => $result->methods,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'provider' => new external_value(PARAM_TEXT, 'Gateway that will handle the payment'),
            'supports_payment_methods' => new external_value(PARAM_BOOL,
                'False when the gateway shows its own picker — skip straight to create_checkout'),
            'methods' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Pass this back as payment_method_id at checkout'),
                    'name_en' => new external_value(PARAM_TEXT, 'Method name in English'),
                    'name_ar' => new external_value(PARAM_TEXT, 'Method name in Arabic'),
                    'logo' => new external_value(PARAM_URL, 'Logo image URL'),
                    'redirect' => new external_value(PARAM_BOOL,
                        'True when paying opens a page (card 3-D Secure); false when it returns a reference code'),
                ])
            ),
        ]);
    }
}
