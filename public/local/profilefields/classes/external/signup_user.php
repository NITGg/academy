<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_profilefields\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_user;
use local_profilefields\signup;
use local_profilefields\signup_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates an account through the site's own sign-up flow.
 *
 * The replacement for `auth_email_signup_user`. That function is stock Moodle's
 * sign-up: it demands a username the site no longer asks anyone for, never sees
 * the consent checkbox (core runs the sign-up plugin callbacks on the web form
 * only), and stores an empty country because the browser's hidden inputs are the
 * only thing that fills those in.
 *
 * The parameters are deliberately the same as the core function's, minus
 * `username` and plus `consent`, so a client moves over by changing the function
 * name and dropping the username it was inventing.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php).
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signup_user extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email' => new external_value(core_user::get_property_type('email'), 'A valid and unique email address'),
            'password' => new external_value(core_user::get_property_type('password'), 'Plain text password'),
            'firstname' => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user'),
            'lastname' => new external_value(core_user::get_property_type('lastname'), 'The family name of the user'),
            'consent' => new external_value(PARAM_BOOL,
                'Whether the user agreed to the policies. Required when get_signup_form reports consent.required.',
                VALUE_DEFAULT, false),
            'email2' => new external_value(core_user::get_property_type('email'),
                'Only send this when get_signup_form lists an "email2" field; it is then checked against email.',
                VALUE_DEFAULT, ''),
            'city' => new external_value(core_user::get_property_type('city'),
                'Home city. Left out, the site default is used.', VALUE_DEFAULT, ''),
            'country' => new external_value(core_user::get_property_type('country'),
                'Home country code. Left out, it follows the phone field or the site default.', VALUE_DEFAULT, ''),
            'username' => new external_value(core_user::get_property_type('username'),
                'Only used when the site still asks for a username; ignored while it builds one from the email.',
                VALUE_DEFAULT, ''),
            'customprofilefields' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'The type of the custom field'),
                    'name' => new external_value(PARAM_ALPHANUMEXT, 'The field name, as returned by get_signup_form '
                        . '(e.g. profile_field_phone)'),
                    'value' => new external_value(PARAM_RAW, 'The value. A phone field takes either an encoded JSON object '
                        . '{"country":"EG","number":"1012345678"} or the string "EG:1012345678".'),
                ]), 'User custom fields (also known as user profile fields)', VALUE_DEFAULT, []
            ),
            'recaptcharesponse' => new external_value(PARAM_NOTAGS, 'Recaptcha response', VALUE_DEFAULT, ''),
            'redirect' => new external_value(PARAM_LOCALURL, 'Redirect the user to this site url after confirmation.',
                VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Create the account.
     *
     * @param string $email a valid and unique email address
     * @param string $password plain text password
     * @param string $firstname the first name(s) of the user
     * @param string $lastname the family name of the user
     * @param bool $consent whether the user agreed to the policies
     * @param string $email2 the repeated address, when the site asks for one
     * @param string $city home city of the user
     * @param string $country home country code
     * @param string $username only used when the site still asks for one
     * @param array $customprofilefields user custom fields (also known as user profile fields)
     * @param string $recaptcharesponse recaptcha response
     * @param string $redirect site url to redirect the user to after confirmation
     * @return array success flag, the username created, and any field errors
     */
    public static function execute($email, $password, $firstname, $lastname, $consent = false, $email2 = '', $city = '',
            $country = '', $username = '', $customprofilefields = [], $recaptcharesponse = '', $redirect = '') {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'email' => $email,
            'password' => $password,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'consent' => $consent,
            'email2' => $email2,
            'city' => $city,
            'country' => $country,
            'username' => $username,
            'customprofilefields' => $customprofilefields,
            'recaptcharesponse' => $recaptcharesponse,
            'redirect' => $redirect,
        ]);

        // format_text()/format_string() need a context, and so does the consent label.
        $PAGE->set_context(context_system::instance());

        signup_api::require_signup_enabled();
        signup_api::require_libs();

        $input = [
            'email' => $params['email'],
            'password' => $params['password'],
            'firstname' => $params['firstname'],
            'lastname' => $params['lastname'],
            'email2' => $params['email2'],
            'city' => $params['city'],
            'country' => $params['country'],
            'username' => $params['username'],
            'consent' => $params['consent'],
        ];

        [$customvalues, $customerrors] = self::read_custom_fields($params['customprofilefields']);
        $data = signup_api::prepare_data($input + $customvalues);

        $errors = $customerrors + signup_api::validate($data, $params['recaptcharesponse']);
        if (!empty($errors)) {
            return ['success' => false, 'username' => '', 'warnings' => self::as_warnings($errors)];
        }

        $user = signup_api::create_user($data, $params['redirect']);

        return ['success' => true, 'username' => $user->username, 'warnings' => []];
    }

    /**
     * Turn the submitted custom fields into form-shaped values.
     *
     * Mirrors what `auth_email_signup_user` does - each value is checked against the
     * field's own parameter type, and a JSON value is decoded so a composite field
     * (the phone field) arrives as the array its validator expects. Two departures:
     * a phone value may also be sent in its stored "ISO:number" form, and a missing
     * required field comes back as a field error rather than an exception, so the
     * client can point at the box instead of showing a stack-trace message.
     *
     * @param array $submitted [['type' => .., 'name' => .., 'value' => ..], ..]
     * @return array [values keyed by inputname, errors keyed by inputname]
     */
    protected static function read_custom_fields(array $submitted): array {
        $allowed = [];
        $datatypes = [];
        $required = [];
        foreach (profile_get_signup_fields() as $field) {
            $allowed[$field->object->inputname] = $field->object->get_field_properties();
            $datatypes[$field->object->inputname] = $field->datatype;
            if ($field->object->is_required()) {
                $required[$field->object->inputname] = true;
            }
        }

        $values = [];
        $errors = [];
        foreach ($submitted as $one) {
            $name = $one['name'];
            if (!isset($allowed[$name])) {
                throw new \invalid_parameter_exception('Invalid field ' . $name);
            }

            [$type, $allownull] = $allowed[$name];
            validate_param($one['value'], $type, $allownull);

            $values[$name] = self::field_value($datatypes[$name], (string) $one['value']);
            if ($one['value'] !== '' && $one['value'] !== null) {
                unset($required[$name]);
            }
        }

        foreach (array_keys($required) as $name) {
            // A field the client did send is validated by the field itself; this only
            // catches one it left out altogether.
            if (!array_key_exists($name, $values)) {
                $errors[$name] = get_string('required');
            }
        }

        return [$values, $errors];
    }

    /**
     * Decode one submitted custom-field value into what the field's validator wants.
     *
     * @param string $datatype the field's datatype (text, menu, phone, ...)
     * @param string $value the raw submitted value
     * @return mixed string, or an array for a composite field
     */
    protected static function field_value(string $datatype, string $value) {
        // A text area (and any other field sending several parts) encodes its value
        // as JSON, exactly as it does for auth_email_signup_user.
        $decoded = json_decode($value, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // The phone field is stored as "ISO:number", and that is what a client reading
        // a profile back gets - so accept it on the way in too.
        if ($datatype === 'phone' && preg_match('/^([A-Za-z]{2}):(.+)$/', trim($value), $m)) {
            return ['country' => strtoupper($m[1]), 'number' => $m[2]];
        }

        return $value;
    }

    /**
     * Present field errors the way core's sign-up service does.
     *
     * @param array $errors element name => message
     * @return array[] warning records
     */
    protected static function as_warnings(array $errors): array {
        $warnings = [];
        foreach ($errors as $item => $message) {
            $warnings[] = [
                'item' => $item === signup::CONSENT ? 'consent' : $item,
                'itemid' => 0,
                'warningcode' => 'fielderror',
                'message' => s($message),
            ];
        }
        return $warnings;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if the account was created, false otherwise.'),
            'username' => new external_value(PARAM_RAW,
                'The username the site created. This is what the user logs in with, so keep it.'),
            'warnings' => new external_warnings('The field the error belongs to ("consent" for the agreement checkbox)',
                'Always 0', 'fielderror, with the message to show the user'),
        ]);
    }
}
